<?php

namespace App\Jobs;

use App\Models\StaffEvent;
use App\Services\Firebase\StaffPushNotifier;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * Mengirim push FCM untuk satu StaffEvent. Tidak masuk antrean: dijalankan setelah respons
 * terkirim (dispatchAfterResponse) supaya tidak butuh queue worker dan tidak memperlambat kasir.
 */
class SendStaffPush
{
    use Dispatchable;

    public function __construct(public readonly StaffEvent $event) {}

    public function handle(StaffPushNotifier $notifier): void
    {
        try {
            $notifier->notify($this->event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
