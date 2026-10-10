<?php

namespace App\Actions\Order;

use App\Models\Branch;
use App\Models\Config;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Shift;
use App\Models\Table;
use App\Services\Tripay\TripayClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    public function __construct(private readonly TripayClient $tripay) {}

    /**
     * Buat pesanan dari checkout customer. Semua harga dihitung ulang di server.
     *
     * @param  array<string, mixed>  $data  hasil validasi StoreOrderRequest
     *
     * @throws ValidationException
     * @throws \App\Services\Tripay\TripayException
     */
    public function handle(array $data): Order
    {
        $branch = Branch::findOrFail($data['branch_id']);
        $table = $this->resolveTable($branch, $data);
        $lines = $this->buildLines($branch, $data['items']);

        $subtotal = collect($lines)->sum(fn ($line) => $line['lineTotal']);
        $tax = (int) round($subtotal * $this->taxPercent($branch) / 100);
        $isQris = $data['payment_method'] === 'qris';

        return DB::transaction(function () use ($data, $branch, $table, $lines, $subtotal, $tax, $isQris) {
            $order = Order::create([
                'branch_id' => $branch->id,
                'table_id' => $table?->id,
                'shift_id' => $this->activeShift($branch)?->id,
                'customer_name' => $data['customer']['name'],
                'customer_phone' => $data['customer']['phone'],
                'customer_email' => $data['customer']['email'] ?? null,
                'order_type' => $data['order_type'],
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_status' => 'unpaid',
                'total_amount' => $subtotal + $tax,
                'tax_amount' => $tax,
                'expires_at' => $isQris ? now()->addMinutes((int) config('services.tripay.expiry_minutes')) : null,
            ]);

            foreach ($lines as $line) {
                $item = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'qty' => $line['qty'],
                    'price' => $line['product']->price,
                    'notes' => $line['notes'],
                    'status' => 'pending',
                ]);

                foreach ($line['addons'] as $addon) {
                    $item->addons()->create([
                        'addon_id' => $addon->id,
                        'name' => $addon->name,
                        'price' => $addon->price,
                    ]);
                }
            }

            $order->load('items.addons');

            if ($isQris) {
                $this->createQrisPayment($order);
            }

            return $order->load(['table', 'payment', 'items.addons']);
        });
    }

    private function resolveTable(Branch $branch, array $data): ?Table
    {
        if ($data['order_type'] !== 'dine_in') {
            return null;
        }

        $table = Table::where('branch_id', $branch->id)
            ->where('qr_token', $data['table_token'])
            ->where('is_active', true)
            ->first();

        if (! $table) {
            throw ValidationException::withMessages([
                'table_token' => 'QR meja tidak valid. Silakan scan ulang QR di meja.',
            ]);
        }

        return $table;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{product: Product, qty: int, notes: ?string, addons: \Illuminate\Support\Collection<int, ProductAddon>, lineTotal: int}>
     */
    private function buildLines(Branch $branch, array $items): array
    {
        $products = Product::with('addonGroups.addons')
            ->where('branch_id', $branch->id)
            ->whereIn('id', collect($items)->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => 'Produk tidak ditemukan di cabang ini.',
                ]);
            }

            if (! $product->is_available) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" => "{$product->name} sedang habis.",
                ]);
            }

            $addons = $this->resolveAddons($product, $item['addon_ids'] ?? [], $index);

            $unitPrice = $product->price + $addons->sum('price');

            $lines[] = [
                'product' => $product,
                'qty' => (int) $item['qty'],
                'notes' => filled($item['notes'] ?? null) ? trim($item['notes']) : null,
                'addons' => $addons,
                'lineTotal' => $unitPrice * (int) $item['qty'],
            ];
        }

        return $lines;
    }

    /**
     * Pastikan addon milik produk, tersedia, dan jumlah pilihan per grup sesuai aturan.
     *
     * @param  array<int, int>  $addonIds
     * @return \Illuminate\Support\Collection<int, ProductAddon>
     */
    private function resolveAddons(Product $product, array $addonIds, int $index)
    {
        $selected = collect();

        foreach ($product->addonGroups as $group) {
            $chosen = $group->addons->whereIn('id', $addonIds);
            $min = $group->is_required ? max($group->min_qty, 1) : $group->min_qty;

            if ($chosen->count() < $min || $chosen->count() > $group->max_qty) {
                throw ValidationException::withMessages([
                    "items.$index.addon_ids" => "Pilihan \"{$group->name}\" untuk {$product->name} belum sesuai.",
                ]);
            }

            if ($chosen->contains(fn (ProductAddon $addon) => ! $addon->is_available)) {
                throw ValidationException::withMessages([
                    "items.$index.addon_ids" => "Ada pilihan \"{$group->name}\" yang sedang habis.",
                ]);
            }

            $selected = $selected->concat($chosen);
        }

        // Addon yang tidak dikenal (bukan milik produk ini) ditolak.
        if ($selected->count() !== count(array_unique($addonIds))) {
            throw ValidationException::withMessages([
                "items.$index.addon_ids" => "Ada pilihan yang tidak valid untuk {$product->name}.",
            ]);
        }

        return $selected->values();
    }

    private function createQrisPayment(Order $order): void
    {
        $data = $this->tripay->createTransaction($order);

        $order->payment()->create([
            'merchant_ref' => $order->code,
            'reference' => $data['reference'] ?? null,
            'method' => $data['payment_method'] ?? config('services.tripay.payment_method'),
            'qr_url' => $data['qr_url'] ?? null,
            'qr_string' => $data['qr_string'] ?? null,
            'checkout_url' => $data['checkout_url'] ?? null,
            'amount' => $order->total_amount,
            'fee_amount' => (int) ($data['fee_customer'] ?? 0),
            'status' => 'unpaid',
            'expires_at' => isset($data['expired_time']) ? now()->setTimestamp((int) $data['expired_time']) : $order->expires_at,
            'created_at' => now(),
        ]);
    }

    /** Persen pajak per cabang dari tabel configs (key "tax"), default 0. */
    private function taxPercent(Branch $branch): float
    {
        return (float) (Config::where('branch_id', $branch->id)->where('key', 'tax')->value('value') ?? 0);
    }

    private function activeShift(Branch $branch): ?Shift
    {
        return Shift::where('status', 'active')
            ->whereHas('user', fn ($user) => $user->where('branch_id', $branch->id))
            ->latest('start_time')
            ->first();
    }
}
