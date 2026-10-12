<?php

namespace App\Actions\Kitchen;

use App\Actions\Order\SyncOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StaffEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateItemStatus
{
    /** Urutan status yang boleh dilewati item: pending -> cooking -> ready. */
    private const FLOW = ['pending' => 0, 'cooking' => 1, 'ready' => 2];

    public function __construct(
        private readonly RefreshBatch $refreshBatch,
        private readonly SyncOrderStatus $syncOrderStatus,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(OrderItem $item, string $status): OrderItem
    {
        $order = $item->order;

        if ($order->payment_status !== 'paid' || $order->status === 'cancelled') {
            $this->fail('Pesanan ini belum lunas atau sudah dibatalkan.');
        }

        if ($status === $item->status) {
            return $item; // sudah di status itu, anggap berhasil
        }

        if ($item->children()->exists()) {
            return $this->advanceBundle($item, $status);
        }

        if (self::FLOW[$status] !== self::FLOW[$item->status] + 1) {
            $this->fail("Status tidak bisa diubah dari {$item->status} ke {$status}.");
        }

        $wasCompleted = $order->status === 'completed';

        DB::transaction(function () use ($item, $status, $order) {
            $item->update(['status' => $status]);

            if ($item->batch) {
                $this->refreshBatch->handle($item->batch);
            }

            $this->syncOrderStatus->handle($order);
        });

        $this->emitEvents($order, $wasCompleted);

        return $item->refresh();
    }

    /** Item paket: semua komponen yang belum sampai ke status itu ikut dimajukan. */
    private function advanceBundle(OrderItem $item, string $status): OrderItem
    {
        $order = $item->order;
        $wasCompleted = $order->status === 'completed';

        DB::transaction(function () use ($item, $status, $order) {
            $item->children()->with('batch')->get()
                ->filter(fn (OrderItem $child) => self::FLOW[$child->status] < self::FLOW[$status])
                ->each(function (OrderItem $child) use ($status) {
                    $child->update(['status' => $status]);

                    if ($child->batch) {
                        $this->refreshBatch->handle($child->batch);
                    }
                });

            $this->syncOrderStatus->handle($order);
        });

        $this->emitEvents($order, $wasCompleted);

        return $item->refresh();
    }

    /** Pesanan yang baru jadi completed memberi tahu kasir bahwa makanan siap diantar. */
    private function emitEvents(Order $order, bool $wasCompleted): void
    {
        $order->refresh();

        StaffEvent::emit(StaffEvent::KITCHEN_UPDATED, $order);

        if (! $wasCompleted && $order->status === 'completed') {
            StaffEvent::emit(StaffEvent::ORDER_READY, $order);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
