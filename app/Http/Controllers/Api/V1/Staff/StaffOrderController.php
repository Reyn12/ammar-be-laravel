<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Order\ConfirmCashPayment;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffOrderResource;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffOrderController extends Controller
{
    private const WITH = ['table', 'items.product.category', 'items.addons.addon.group'];

    /**
     * Daftar pesanan cabang. Filter ?status= (boleh beberapa, pisahkan koma):
     *   paid          pesanan lunas yang masih berjalan (belum selesai)
     *   pending_cash  pesanan tunai yang belum dikonfirmasi kasir
     *   completed     pesanan selesai
     * Tambahan: ?shift_id=, ?limit= (maks 500).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'shift_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $statuses = array_filter(explode(',', (string) $request->query('status')));

        $orders = Order::with(self::WITH)
            ->where('branch_id', $request->user()->branch_id)
            ->when($request->filled('shift_id'), fn ($query) => $query->where('shift_id', $request->integer('shift_id')))
            ->when($statuses, fn ($query) => $query->where(function ($query) use ($statuses) {
                foreach ($statuses as $status) {
                    match ($status) {
                        'paid' => $query->orWhere(fn ($q) => $q->where('payment_status', 'paid')->where('status', '!=', 'completed')),
                        'pending_cash' => $query->orWhere(fn ($q) => $q->where('payment_method', 'cash')->where('payment_status', 'unpaid')->where('status', '!=', 'cancelled')),
                        'completed' => $query->orWhere('status', 'completed'),
                        default => null,
                    };
                }
            }))
            ->latest('id')
            ->limit($request->integer('limit', 100))
            ->get();

        return ApiResponse::success(StaffOrderResource::collection($orders)->resolve());
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->ensureSameBranch($request, $order);

        return ApiResponse::success((new StaffOrderResource($order->load(self::WITH)))->resolve());
    }

    public function confirmCash(Request $request, Order $order, ConfirmCashPayment $action): JsonResponse
    {
        $this->ensureSameBranch($request, $order);

        $data = $request->validate([
            'received_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $confirmed = $action->handle($order, $request->user(), $data['received_amount'] ?? null);

        return ApiResponse::success((new StaffOrderResource($confirmed))->resolve(), 'Pembayaran tunai dikonfirmasi.');
    }

    private function ensureSameBranch(Request $request, Order $order): void
    {
        abort_if($order->branch_id !== $request->user()->branch_id, 404);
    }
}
