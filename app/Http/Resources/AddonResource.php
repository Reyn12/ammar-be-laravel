<?php

namespace App\Http\Resources;

use App\Models\ProductAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductAddon */
class AddonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'name' => $this->name,
            'price' => $this->price,
            'is_available' => $this->is_available,
        ];
    }
}
