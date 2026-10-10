<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class MenuProductResource extends JsonResource
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
            'image_url' => $this->image_url ? (str_starts_with($this->image_url, 'http') ? $this->image_url : asset($this->image_url)) : null,
            'name' => $this->name,
            'price' => $this->price,
            'is_available' => $this->is_available,
            'addon_groups' => AddonGroupResource::collection($this->whenLoaded('addonGroups')),
        ];
    }
}
