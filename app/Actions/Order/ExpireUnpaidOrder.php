<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\StaffEvent;

class ExpireUnpaidOrder
{
    /**
     * Batalkan order QRIS yang melewati batas bayar. Order tunai tidak punya batas waktu.
     */
    public function handle(Order $order): Order
    {
        $isOverdue = $order->payment_method === 'qris'
            && $order->payment_status === 'unpaid'
            && $order->expires_at !== null
            && $order->expires_at->isPast();

        if ($isOverdue) {
            $order->update(['payment_status' => 'expired', 'status' => 'cancelled']);
            $order->payment?->update(['status' => 'expired']);
            StaffEvent::emit(StaffEvent::ORDER_CANCELLED, $order);
        }

        return $order;
    }
}
