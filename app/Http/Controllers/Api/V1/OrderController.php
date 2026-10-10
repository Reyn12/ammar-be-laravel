<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Order\CreateOrder;
use App\Actions\Order\ExpireUnpaidOrder;
use App\Actions\Payment\ApplyTripayStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Tripay\TripayClient;
use App\Services\Tripay\TripayException;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CreateOrder $action, TripayClient $tripay): JsonResponse
    {
        $data = $request->validated();

        if ($data['payment_method'] === 'qris' && ! $tripay->isConfigured()) {
            return response()->json(['message' => 'Pembayaran QRIS belum tersedia. Silakan pilih tunai.'], 503);
        }

        try {
            $order = $action->handle($data);
        } catch (TripayException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(
        string $code,
        ExpireUnpaidOrder $expire,
        ApplyTripayStatus $applyStatus,
        TripayClient $tripay,
    ): OrderResource {
        $order = Order::with(['table', 'payment', 'items.addons'])->where('code', $code)->firstOrFail();

        // Callback Tripay belum tentu sampai (mis. di lokal), jadi cek langsung ke Tripay selagi menunggu bayar.
        $payment = $order->payment;
        if ($payment && $payment->status === 'unpaid' && $payment->reference && $tripay->isConfigured()) {
            if ($detail = $tripay->transactionDetail($payment->reference)) {
                $applyStatus->handle($payment, $detail);
                $order->refresh()->load(['table', 'payment', 'items.addons']);
            }
        }

        $expire->handle($order);

        return new OrderResource($order->refresh()->load(['table', 'payment', 'items.addons']));
    }
}
