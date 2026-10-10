<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Produk untuk aplikasi staf (kelola menu). Bentuknya mengikuti ProductModel di Flutter.
 *
 * @mixin Product
 */
class StaffProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'image_url' => $this->image_url && ! str_starts_with($this->image_url, 'http') ? asset($this->image_url) : $this->image_url,
            'name' => $this->name,
            'price' => $this->price,
            'is_available' => $this->is_available,
            'addon_groups' => $this->addonGroups->map(fn (ProductAddonGroup $group) => [
                'id' => $group->id,
                'product_id' => $group->product_id,
                'name' => $group->name,
                'type' => $group->is_required && $group->max_qty === 1 ? 'variant' : 'addon',
                'is_required' => $group->is_required,
                'min_qty' => $group->min_qty,
                'max_qty' => $group->max_qty,
                'addons' => $group->addons->map(fn (ProductAddon $addon) => [
                    'id' => $addon->id,
                    'group_id' => $addon->group_id,
                    'name' => $addon->name,
                    'price' => $addon->price,
                    'is_available' => $addon->is_available,
                ])->values(),
            ])->values(),
        ];
    }
}
