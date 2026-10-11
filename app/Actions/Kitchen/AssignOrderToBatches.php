<?php

namespace App\Actions\Kitchen;

use App\Models\Batch;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AssignOrderToBatches
{
    public function __construct(private readonly RefreshBatch $refreshBatch) {}

    /**
     * Order Batching (SKPL-F-010): item menu yang sama dari beberapa pesanan dikelompokkan
     * dalam satu batch agar dapur memasaknya sekaligus. Satu batch memuat maksimal
     * Batch::MAX_NOTES nota; kalau penuh, dibuatkan batch baru.
     *
     * Dipanggil sekali saat pesanan lunas. Item yang sudah punya batch dilewati,
     * begitu juga item paket (induk) karena komponennya yang masuk batch.
     */
    public function handle(Order $order): void
    {
        $order->loadMissing('items');

        // Item paket (induk) tidak dimasak sendiri; yang di-batch adalah komponennya.
        $parentIds = $order->items->pluck('parent_item_id')->filter()->unique();

        $order->items
            ->whereNull('batch_id')
            ->reject(fn ($item) => $parentIds->contains($item->id))
            ->groupBy('product_id')
            ->each(function ($items, $productId) use ($order) {
                DB::transaction(function () use ($items, $productId, $order) {
                    $batch = Batch::where('branch_id', $order->branch_id)
                        ->where('product_id', $productId)
                        ->where('status', 'open')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first()
                        ?? Batch::create([
                            'branch_id' => $order->branch_id,
                            'product_id' => $productId,
                            'status' => 'open',
                            'total_qty' => 0,
                            'order_count' => 0,
                        ]);

                    foreach ($items as $item) {
                        $item->update(['batch_id' => $batch->id]);
                    }

                    $this->refreshBatch->handle($batch);
                });
            });
    }
}
