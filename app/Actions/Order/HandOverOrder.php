<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\StaffEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandOverOrder
{
    /**
     * Kasir menandai makanan sudah diserahkan ke pelanggan (bersama struk).
     *
     * @throws ValidationException
     */
    public function handle(Order $order): Order
    {
        $order = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'completed') {
                throw ValidationException::withMessages(['order' => 'Pesanan ini belum selesai dimasak.']);
            }

            if ($order->handed_over_at === null) {
                $order->update(['handed_over_at' => now()]);
            }

            return $order;
        });

        if ($order->wasChanged('handed_over_at')) {
            StaffEvent::emit(StaffEvent::ORDER_HANDED_OVER, $order);
        }

        return $order;
    }
}
