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
            // Paket otomatis habis kalau salah satu isinya habis atau sudah diarsipkan.
            'is_available' => $this->is_available && $this->componentsAvailable(),
            'addon_groups' => AddonGroupResource::collection($this->whenLoaded('addonGroups')),
            // Isi paket (kosong untuk menu biasa)
            'components' => $this->whenLoaded('components', fn () => $this->components->map(fn ($component) => [
                'name' => $component->component?->name,
                'qty' => $component->qty,
            ])->values()),
        ];
    }

    private function componentsAvailable(): bool
    {
        if (! $this->relationLoaded('components')) {
            return true;
        }

        return $this->components->every(fn ($component) => (bool) $component->component?->is_available);
    }
}
