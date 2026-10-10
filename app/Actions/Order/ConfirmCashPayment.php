<?php

namespace App\Actions\Order;

use App\Actions\Kitchen\AssignOrderToBatches;
use App\Actions\Notification\SendOrderReceipt;
use App\Models\Order;
use App\Models\Shift;
use App\Models\StaffEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmCashPayment
{
    public function __construct(
        private readonly SendOrderReceipt $sendReceipt,
        private readonly AssignOrderToBatches $assignBatches,
    ) {}

    /**
     * Kasir mengonfirmasi uang tunai sudah diterima (SKPL-F-006).
     *
     * @throws ValidationException
     */
    public function handle(Order $order, User $cashier, ?int $receivedAmount = null): Order
    {
        $shift = Shift::where('user_id', $cashier->id)->where('status', 'active')->first();

        if (! $shift) {
            $this->fail('Buka shift dulu sebelum mengonfirmasi pembayaran tunai.');
        }

        $order = DB::transaction(function () use ($order, $cashier, $shift, $receivedAmount) {
            // Kunci baris supaya dua kasir tidak mengonfirmasi pesanan yang sama bersamaan.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_method !== 'cash') {
                $this->fail('Pesanan ini tidak dibayar tunai.');
            }

            if ($order->payment_status === 'paid') {
                $this->fail('Pesanan ini sudah dikonfirmasi lunas.');
            }

            if ($order->status === 'cancelled') {
                $this->fail('Pesanan ini sudah dibatalkan.');
            }

            if ($receivedAmount !== null && $receivedAmount < $order->total_amount) {
                $this->fail('Uang yang diterima kurang dari total tagihan.');
            }

            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'cash_received' => $receivedAmount ?? $order->total_amount,
                'confirmed_by' => $cashier->id,
                // Tunai masuk ke kas shift yang sedang mengonfirmasi.
                'shift_id' => $shift->id,
            ]);

            $this->assignBatches->handle($order);

            return $order;
        });

        StaffEvent::emit(StaffEvent::ORDER_PAID, $order);
        $this->sendReceipt->handle($order);

        return $order->load(['table', 'items.product.category', 'items.addons']);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['order' => $message]);
    }
}
