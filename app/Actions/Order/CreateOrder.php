<?php

namespace App\Actions\Order;

use App\Models\Branch;
use App\Models\Config;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Shift;
use App\Models\StaffEvent;
use App\Models\Table;
use App\Services\Tripay\TripayClient;
use App\Services\Tripay\TripayException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
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
     * @throws TripayException
     */
    public function handle(array $data): Order
    {
        $branch = Branch::findOrFail($data['branch_id']);
        $table = $this->resolveTable($branch, $data);
        $lines = $this->buildLines($branch, $data['items']);

        $subtotal = collect($lines)->sum(fn ($line) => $line['lineTotal']);
        $tax = (int) round($subtotal * $this->taxPercent($branch) / 100);
        $isQris = $data['payment_method'] === 'qris';

        $order = DB::transaction(function () use ($data, $branch, $table, $lines, $subtotal, $tax, $isQris) {
            // Dua pesanan yang masuk bersamaan bisa mendapat nomor urut sama; yang kedua mengulang.
            $order = retry(3, fn () => Order::create([
                'branch_id' => $branch->id,
                'table_id' => $table?->id,
                'customer_id' => $data['customer_id'] ?? null,
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
            ]), 0, fn (\Throwable $e) => $e instanceof UniqueConstraintViolationException);

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

                $this->createComponentItems($order, $item, $line);
            }

            $order->load('items.addons');

            if ($isQris) {
                $this->createQrisPayment($order);
            }

            return $order->load(['table', 'payment', 'items.addons']);
        });

        // Pesanan tunai langsung muncul di daftar "menunggu konfirmasi" kasir.
        StaffEvent::emit(StaffEvent::ORDER_CREATED, $order);

        return $order;
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
     * @return array<int, array{product: Product, qty: int, notes: ?string, addons: Collection<int, ProductAddon>, lineTotal: int}>
     */
    private function buildLines(Branch $branch, array $items): array
    {
        $products = Product::with(['addonGroups.addons', 'components.component'])
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

            $this->ensureComponentsAvailable($product, $index);

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

    /** Paket tidak bisa dipesan kalau salah satu isinya habis atau sudah diarsipkan. */
    private function ensureComponentsAvailable(Product $product, int $index): void
    {
        foreach ($product->components as $component) {
            if (! $component->component?->is_available) {
                $name = $component->component?->name ?? 'salah satu isi paket';

                throw ValidationException::withMessages([
                    "items.$index.product_id" => "{$product->name} belum bisa dipesan, {$name} sedang habis.",
                ]);
            }
        }
    }

    /**
     * Paket dipecah jadi item dapur per komponen (harga 0, harganya sudah di item paket).
     * Pilihan paket dan catatan customer ikut ke komponen yang menandai receives_options.
     *
     * @param  array{product: Product, qty: int, notes: ?string, addons: Collection<int, ProductAddon>}  $line
     */
    private function createComponentItems(Order $order, OrderItem $parent, array $line): void
    {
        foreach ($line['product']->components as $component) {
            $child = $order->items()->create([
                'parent_item_id' => $parent->id,
                'product_id' => $component->component_id,
                'product_name' => $component->component->name,
                'qty' => $component->qty * $line['qty'],
                'price' => 0,
                'notes' => $component->receives_options ? $line['notes'] : null,
                'status' => 'pending',
            ]);

            if (! $component->receives_options) {
                continue;
            }

            foreach ($line['addons'] as $addon) {
                $child->addons()->create([
                    'addon_id' => $addon->id,
                    'name' => $addon->name,
                    'price' => 0,
                ]);
            }
        }
    }

    /**
     * Pastikan addon milik produk, tersedia, dan jumlah pilihan per grup sesuai aturan.
     *
     * @param  array<int, int>  $addonIds
     * @return Collection<int, ProductAddon>
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
