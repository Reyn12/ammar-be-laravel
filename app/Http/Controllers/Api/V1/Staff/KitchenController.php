<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Kitchen\ProcessBatch;
use App\Actions\Kitchen\UpdateItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffOrderResource;
use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KitchenController extends Controller
{
    /** Pesanan lunas yang masih dikerjakan dapur (belum semua item disajikan). */
    public function orders(Request $request): JsonResponse
    {
        $orders = Order::with(['table', 'items.product.category', 'items.addons.addon.group'])
            ->where('branch_id', $request->user()->branch_id)
            ->where('payment_status', 'paid')
            ->whereIn('status', ['pending', 'cooking'])
            ->latest('id')
            ->get();

        return ApiResponse::success(StaffOrderResource::collection($orders)->resolve());
    }

    /**
     * Batch yang masih punya item menunggu diproses. Hanya nota yang masih pending
     * yang ditampilkan, sehingga batch mengecil saat sebagian nota diproses.
     */
    public function batches(Request $request): JsonResponse
    {
        $batches = Batch::with([
            'product.category',
            'items' => fn ($items) => $items->where('status', 'pending')->orderBy('order_id'),
            'items.order.table',
        ])
            ->where('branch_id', $request->user()->branch_id)
            ->whereIn('status', ['open', 'full'])
            ->orderBy('id')
            ->get();

        return ApiResponse::success($batches->map(fn (Batch $batch) => [
            'id' => $batch->id,
            'category_name' => $batch->product?->category?->name,
            'product_id' => $batch->product_id,
            'product_name' => $batch->product?->name,
            'total_qty' => $batch->total_qty,
            'status' => 'pending',
            'tables' => $batch->items->groupBy('order_id')->map(function ($items) {
                $order = $items->first()->order;

                return [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'queue_number' => $order->queueNumber(),
                    'table_label' => $order->order_type === 'takeaway' ? 'Takeaway' : 'Meja '.($order->table?->table_number ?? '-'),
                    'qty' => (int) $items->sum('qty'),
                ];
            })->values(),
        ])->values());
    }

    public function processBatch(Request $request, Batch $batch, ProcessBatch $action): JsonResponse
    {
        abort_if($batch->branch_id !== $request->user()->branch_id, 404);

        $data = $request->validate([
            'order_ids' => ['nullable', 'array'],
            'order_ids.*' => ['integer'],
        ]);

        $action->handle($batch, $data['order_ids'] ?? null);

        return ApiResponse::success(null, 'Batch diproses.');
    }

    public function updateItemStatus(Request $request, OrderItem $orderItem, UpdateItemStatus $action): JsonResponse
    {
        abort_if($orderItem->order->branch_id !== $request->user()->branch_id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['cooking', 'ready'])],
        ]);

        $action->handle($orderItem, $data['status']);

        return ApiResponse::success(null, 'Status item diperbarui.');
    }
}
