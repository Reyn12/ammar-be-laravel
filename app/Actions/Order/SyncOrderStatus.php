<?php

namespace App\Actions\Order;

use App\Models\Order;

class SyncOrderStatus
{
    /**
     * Status pesanan mengikuti status item-nya:
     *  semua disajikan -> completed (pesanan selesai)
     *  ada yang dimasak / sebagian disajikan -> cooking
     *  selainnya -> pending
     */
    public function handle(Order $order): Order
    {
        if ($order->status === 'cancelled') {
            return $order;
        }

        $statuses = $order->items()->pluck('status');

        $status = match (true) {
            $statuses->isEmpty() => $order->status,
            $statuses->every(fn ($s) => $s === 'ready') => 'completed',
            $statuses->contains(fn ($s) => $s !== 'pending') => 'cooking',
            default => 'pending',
        };

        if ($status !== $order->status) {
            $order->update(['status' => $status]);
        }

        return $order;
    }
}
