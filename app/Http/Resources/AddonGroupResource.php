<?php

namespace App\Http\Resources;

use App\Models\ProductAddonGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductAddonGroup */
class AddonGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            // variant = wajib pilih satu; addon = opsional, boleh banyak
            'type' => $this->is_required && $this->max_qty === 1 ? 'variant' : 'addon',
            'is_required' => $this->is_required,
            'min_qty' => $this->min_qty,
            'max_qty' => $this->max_qty,
            'addons' => AddonResource::collection($this->whenLoaded('addons')),
        ];
    }
}
