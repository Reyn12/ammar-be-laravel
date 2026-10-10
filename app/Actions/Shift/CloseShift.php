<?php

namespace App\Actions\Shift;

use App\Models\Shift;
use Illuminate\Validation\ValidationException;

class CloseShift
{
    /**
     * Tutup shift (SKPL-F-008): bandingkan uang fisik dengan perkiraan sistem
     * = modal awal + total pesanan tunai yang lunas di shift ini.
     *
     * @throws ValidationException
     */
    public function handle(Shift $shift, int $actualCash): Shift
    {
        if (! $shift->isActive()) {
            throw ValidationException::withMessages(['shift' => 'Shift ini sudah ditutup.']);
        }

        $cashTotal = (int) $shift->orders()
            ->where('payment_method', 'cash')
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $shift->update([
            'end_time' => now(),
            'status' => 'closed',
            'expected_cash' => $shift->starting_cash + $cashTotal,
            'actual_cash' => $actualCash,
        ]);

        return $shift;
    }
}
