<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\Fonnte\FonnteClient;
use App\Services\Fonnte\FonnteException;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Mengirim satu notifikasi WhatsApp dan mencatat hasilnya di tabel notifications.
 * Tidak masuk antrean: dijalankan langsung (dispatchAfterResponse) supaya tidak butuh queue worker.
 */
class SendWhatsAppNotification
{
    use Dispatchable;

    public function __construct(public readonly Notification $notification) {}

    public function handle(FonnteClient $fonnte): void
    {
        $notification = $this->notification;

        if ($notification->status === 'sent') {
            return;
        }

        try {
            $fonnte->send($notification->recipient, $notification->message);

            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (FonnteException) {
            $notification->update([
                'status' => 'failed',
                'retry_count' => $notification->retry_count + 1,
            ]);
        }
    }
}
