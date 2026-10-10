<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:retry-failed {--max-retries=3}')]
#[Description('Kirim ulang notifikasi WhatsApp yang gagal terkirim')]
class RetryFailedNotifications extends Command
{
    public function handle(): int
    {
        $notifications = Notification::where('status', 'failed')
            ->where('retry_count', '<', (int) $this->option('max-retries'))
            ->get();

        foreach ($notifications as $notification) {
            $notification->update(['status' => 'retried']);
            SendWhatsAppNotification::dispatchSync($notification);
        }

        $this->info("{$notifications->count()} notifikasi dicoba kirim ulang.");

        return self::SUCCESS;
    }
}
