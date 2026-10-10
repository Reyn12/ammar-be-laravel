<?php

namespace App\Actions\Kitchen;

use App\Actions\Order\SyncOrderStatus;
use App\Models\Batch;
use App\Models\Order;
use App\Models\StaffEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessBatch
{
    public function __construct(
        private readonly RefreshBatch $refreshBatch,
        private readonly SyncOrderStatus $syncOrderStatus,
    ) {}

    /**
     * "Process All": semua item pending di batch (atau hanya nota terpilih) jadi cooking.
     *
     * @param  array<int, int>|null  $orderIds
     *
     * @throws ValidationException
     */
    public function handle(Batch $batch, ?array $orderIds = null): void
    {
        $orderIdsTouched = DB::transaction(function () use ($batch, $orderIds) {
            $items = $batch->items()
                ->where('status', 'pending')
                ->when($orderIds, fn ($query) => $query->whereIn('order_id', $orderIds))
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['batch' => 'Tidak ada item yang bisa diproses di batch ini.']);
            }

            $batch->items()->whereIn('id', $items->pluck('id'))->update(['status' => 'cooking']);

            $this->refreshBatch->handle($batch);

            $touched = $items->pluck('order_id')->unique();
            Order::whereIn('id', $touched)->get()->each(fn (Order $order) => $this->syncOrderStatus->handle($order));

            return $touched;
        });

        Order::whereIn('id', $orderIdsTouched)->get()
            ->each(fn (Order $order) => StaffEvent::emit(StaffEvent::KITCHEN_UPDATED, $order));
    }
}
