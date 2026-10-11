<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tanpa data pribadi (telepon/email) karena endpoint detail pesanan dibuka lewat kode pesanan.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->payment;

        return [
            'code' => $this->code,
            'queue_number' => $this->queueNumber(),
            'order_type' => $this->order_type,
            'status' => $this->status,
            'table_number' => $this->table?->table_number,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'total_amount' => $this->total_amount,
            'tax_amount' => $this->tax_amount,
            'paid_at' => $this->paid_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'items' => $this->items->whereNull('parent_item_id')->map(fn (OrderItem $item) => [
                'product_name' => $item->product_name,
                'qty' => $item->qty,
                'price' => $item->price,
                'notes' => $item->notes,
                'components' => $this->items->where('parent_item_id', $item->id)->map(fn (OrderItem $component) => [
                    'product_name' => $component->product_name,
                    'qty' => $component->qty,
                ])->values(),
                'addons' => $item->addons->map(fn (OrderItemAddon $addon) => [
                    'name' => $addon->name,
                    'price' => $addon->price,
                ])->values(),
            ])->values(),
            'payment' => $payment ? [
                'method' => $payment->method,
                'status' => $payment->status,
                // QR hanya dikirim selama masih menunggu pembayaran
                'qr_url' => $payment->status === 'unpaid' ? $payment->qr_url : null,
                'qr_string' => $payment->status === 'unpaid' ? $payment->qr_string : null,
                'checkout_url' => $payment->status === 'unpaid' ? $payment->checkout_url : null,
                'amount' => $payment->amount,
                'fee_amount' => $payment->fee_amount,
                'total_to_pay' => $payment->amount + $payment->fee_amount,
                'expires_at' => $payment->expires_at,
            ] : null,
        ];
    }
}
