<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class StaffUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'username' => $this->username,
            'name' => $this->name,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
