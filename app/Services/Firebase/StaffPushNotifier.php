<?php

namespace App\Services\Firebase;

use App\Models\DeviceToken;
use App\Models\StaffEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Mengubah StaffEvent menjadi push FCM ke tablet staf di cabang yang sama. Aturannya mengikuti
 * alert di app Flutter: toast kasir untuk tunai baru / QRIS lunas, toast dapur untuk pesanan lunas.
 */
class StaffPushNotifier
{
    public function __construct(private readonly FcmClient $fcm) {}

    public function notify(StaffEvent $event): void
    {
        if (! $this->fcm->isConfigured()) {
            return;
        }

        $message = $this->messageFor($event);
        if ($message === null) {
            return;
        }

        $tokens = DeviceToken::query()
            ->whereHas('user', fn ($user) => $user
                ->where('branch_id', $event->branch_id)
                ->where('is_active', true)
                ->whereIn('role', $message['roles']))
            ->get();

        foreach ($tokens as $deviceToken) {
            try {
                $delivered = $this->fcm->send($deviceToken->token, $message['title'], $message['body'], [
                    'type' => $event->type,
                    'order_id' => $event->order_id,
                ]);

                if (! $delivered) {
                    $deviceToken->delete();
                }
            } catch (FcmException $e) {
                Log::warning('Push FCM gagal', ['event' => $event->id, 'reason' => $e->getMessage()]);
            }
        }
    }

    /**
     * Kirim notifikasi tes ke semua perangkat milik satu akun (tombol bel di app).
     *
     * @return int jumlah perangkat yang menerima
     *
     * @throws FcmException
     */
    public function sendTest(User $user): int
    {
        $delivered = 0;

        foreach ($user->deviceTokens as $deviceToken) {
            $accepted = $this->fcm->send(
                $deviceToken->token,
                'Tes notifikasi',
                'Kalau kamu mendengar bunyi ini, notifikasi pesanan masuk berfungsi.',
                ['type' => 'test'],
            );

            if ($accepted) {
                $delivered++;
            } else {
                $deviceToken->delete();
            }
        }

        return $delivered;
    }

    /** @return array{roles: list<string>, title: string, body: string}|null */
    private function messageFor(StaffEvent $event): ?array
    {
        $data = $event->data ?? [];
        $method = $data['payment_method'] ?? null;
        $detail = trim(($data['place_label'] ?? '').' · '.($data['customer_name'] ?? '-'), ' ·');

        return match (true) {
            $event->type === StaffEvent::ORDER_CREATED && $method === 'cash' => [
                'roles' => ['cashier', 'owner'],
                'title' => 'Pesanan tunai baru',
                'body' => $detail,
            ],
            $event->type === StaffEvent::ORDER_PAID && $method === 'qris' => [
                'roles' => ['cashier', 'owner', 'kitchen'],
                'title' => 'Pesanan baru sudah dibayar',
                'body' => $detail,
            ],
            $event->type === StaffEvent::ORDER_READY => [
                'roles' => ['cashier', 'owner'],
                'title' => 'Pesanan siap diantar',
                'body' => trim('Antrian '.($data['queue_number'] ?? '-').' · '.$detail, ' ·'),
            ],
            // Tunai lunas setelah kasir konfirmasi: kasir sudah tahu, hanya dapur yang perlu.
            $event->type === StaffEvent::ORDER_PAID => [
                'roles' => ['kitchen'],
                'title' => 'Pesanan baru masuk dapur',
                'body' => $detail,
            ],
            default => null,
        };
    }
}
