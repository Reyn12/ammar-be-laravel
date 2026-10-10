<?php

namespace App\Actions\Kitchen;

use App\Models\Batch;

class RefreshBatch
{
    /**
     * Hitung ulang batch dari item-itemnya:
     *  - total_qty / order_count = hanya item yang masih menunggu diproses (pending)
     *  - open    : masih menerima nota baru (kurang dari MAX_NOTES nota pending)
     *  - full    : sudah MAX_NOTES nota pending
     *  - cooking : tidak ada yang pending, masih ada yang dimasak
     *  - done    : semua item sudah disajikan
     */
    public function handle(Batch $batch): Batch
    {
        $items = $batch->items()->get(['order_id', 'qty', 'status']);
        $pending = $items->where('status', 'pending');

        $noteCount = $pending->pluck('order_id')->unique()->count();

        $status = match (true) {
            $pending->isNotEmpty() && $noteCount >= Batch::MAX_NOTES => 'full',
            $pending->isNotEmpty() => 'open',
            $items->contains('status', 'cooking') => 'cooking',
            default => 'done',
        };

        $batch->update([
            'status' => $status,
            'order_count' => $noteCount,
            'total_qty' => (int) $pending->sum('qty'),
        ]);

        return $batch;
    }
}
