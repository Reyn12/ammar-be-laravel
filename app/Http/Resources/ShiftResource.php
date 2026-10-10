<?php

namespace App\Http\Resources;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perlu dimuat lewat Shift::withTotals() supaya hitungan transaksi tersedia.
 *
 * @mixin Shift
 */
class ShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Shift aktif: perkiraan kas dihitung langsung; shift tertutup: nilai yang tersimpan.
        $expectedCash = $this->isActive()
            ? $this->starting_cash + (int) ($this->cash_total ?? 0)
            : $this->expected_cash;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'starting_cash' => $this->starting_cash,
            'expected_cash' => $expectedCash,
            'actual_cash' => $this->actual_cash,
            // selisih uang fisik terhadap perkiraan sistem (negatif = kurang)
            'difference' => $this->actual_cash !== null && $expectedCash !== null
                ? $this->actual_cash - $expectedCash
                : null,
            'cash_order_count' => (int) ($this->cash_order_count ?? 0),
            'qris_order_count' => (int) ($this->qris_order_count ?? 0),
        ];
    }
}
