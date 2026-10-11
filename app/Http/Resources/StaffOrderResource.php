<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pesanan untuk aplikasi staf (kasir/dapur). Bentuknya mengikuti OrderModel di Flutter.
 *
 * @mixin Order
 */
class StaffOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'shift_id' => $this->shift_id,
            'table_id' => $this->table_id,
            'table_number' => $this->table ? (string) $this->table->table_number : null,
            'branch_id' => $this->branch_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'order_type' => $this->order_type,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'total_amount' => $this->total_amount,
            'tax_amount' => $this->tax_amount,
            'cash_received' => $this->cash_received,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            // Komponen paket tidak jadi baris sendiri; mereka muncul di "components" item paketnya.
            'items' => $this->items->whereNull('parent_item_id')->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'category_name' => $item->product?->category?->name,
                'qty' => $item->qty,
                'price' => $item->price,
                'status' => $item->status,
                'notes' => $item->notes,
                'components' => $this->items->where('parent_item_id', $item->id)->map(fn (OrderItem $component) => [
                    'id' => $component->id,
                    'product_id' => $component->product_id,
                    'product_name' => $component->product_name,
                    'qty' => $component->qty,
                    'status' => $component->status,
                    'addons' => $component->addons->pluck('name')->values(),
                ])->values(),
                'addons' => $item->addons->map(fn (OrderItemAddon $addon) => [
                    'id' => $addon->id,
                    'order_item_id' => $addon->order_item_id,
                    'addon_id' => $addon->addon_id,
                    'name' => $addon->name,
                    'price' => $addon->price,
                ])->values(),
            ])->values(),
        ];
    }
}
