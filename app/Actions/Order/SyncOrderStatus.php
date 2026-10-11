<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\OrderItem;

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

        $this->syncBundleItems($order);

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

    /** Item paket: semua komponen disajikan -> ready, semua menunggu -> pending, selainnya cooking. */
    private function syncBundleItems(Order $order): void
    {
        $items = $order->items()->get(['id', 'parent_item_id', 'status']);

        $items->whereNotNull('parent_item_id')->groupBy('parent_item_id')->each(function ($children, $parentId) use ($items) {
            $status = match (true) {
                $children->every(fn ($child) => $child->status === 'ready') => 'ready',
                $children->every(fn ($child) => $child->status === 'pending') => 'pending',
                default => 'cooking',
            };

            if ($items->firstWhere('id', $parentId)?->status !== $status) {
                OrderItem::whereKey($parentId)->update(['status' => $status]);
            }
        });
    }
}
