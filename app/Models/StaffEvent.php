<?php

namespace App\Models;

use App\Jobs\SendStaffPush;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'type', 'order_id', 'data'])]
class StaffEvent extends Model
{
    public $timestamps = false;

    public const ORDER_CREATED = 'order.created';

    public const ORDER_PAID = 'order.paid';

    public const ORDER_CANCELLED = 'order.cancelled';

    public const ORDER_READY = 'order.ready';

    public const ORDER_HANDED_OVER = 'order.handed_over';

    public const KITCHEN_UPDATED = 'kitchen.updated';

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Catat satu event untuk satu pesanan. Data sengaja ringkas; klien mengambil
     * detail lewat endpoint REST.
     */
    public static function emit(string $type, Order $order): self
    {
        $order->loadMissing('table');

        // Jangan pakai $order->table di sini: di dalam turunan Model, itu membaca properti protected
        // Model::$table (nama tabel), bukan relasi table().
        $tableNumber = $order->getRelation('table')?->table_number;

        $event = static::create([
            'branch_id' => $order->branch_id,
            'type' => $type,
            'order_id' => $order->id,
            'data' => [
                'order_id' => $order->id,
                'code' => $order->code,
                'queue_number' => $order->queueNumber(),
                'place_label' => $order->order_type === 'takeaway' ? 'Takeaway' : 'Meja '.($tableNumber ?? '-'),
                'customer_name' => $order->customer_name,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'status' => $order->status,
            ],
        ]);

        SendStaffPush::dispatchAfterResponse($event);

        return $event;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
