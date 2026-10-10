<?php

namespace App\Http\Resources;

use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Table */
class StaffTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'table_number' => $this->table_number,
            'qr_token' => $this->qr_token,
            'is_active' => $this->is_active,
            // Isi QR: halaman customer + token meja
            'qr_url' => rtrim((string) config('app.customer_url'), '/')."/m/{$this->table_number}?t={$this->qr_token}",
        ];
    }
}
