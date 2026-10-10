<?php

namespace App\Actions\Shift;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OpenShift
{
    /**
     * @throws ValidationException
     */
    public function handle(User $cashier, int $startingCash): Shift
    {
        if (Shift::where('user_id', $cashier->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['shift' => 'Kamu masih punya shift yang aktif.']);
        }

        return Shift::create([
            'user_id' => $cashier->id,
            'start_time' => now(),
            'status' => 'active',
            'starting_cash' => $startingCash,
        ]);
    }
}
