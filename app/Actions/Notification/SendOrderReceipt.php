<?php

namespace App\Actions\Notification;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Notification;
use App\Models\Order;
use App\Services\Fonnte\FonnteClient;
use Illuminate\Support\Facades\Log;

class SendOrderReceipt
{
    public function __construct(
        private readonly FonnteClient $fonnte,
        private readonly BuildOrderReceiptMessage $buildMessage,
    ) {}

    /**
     * Kirim detail pesanan + bukti bayar ke WhatsApp customer setelah pesanan lunas.
     * Hanya sekali per pesanan, dan dijalankan setelah response HTTP terkirim.
     */
    public function handle(Order $order): ?Notification
    {
        if (blank($order->customer_phone)) {
            return null;
        }

        if (! $this->fonnte->isConfigured()) {
            Log::info('Fonnte belum dikonfigurasi, struk WhatsApp tidak dikirim.', ['order' => $order->code]);

            return null;
        }

        $alreadySent = Notification::where('order_id', $order->id)->where('channel', 'whatsapp')->exists();

        if ($alreadySent) {
            return null;
        }

        $notification = Notification::create([
            'order_id' => $order->id,
            'channel' => 'whatsapp',
            'recipient' => $order->customer_phone,
            'message' => $this->buildMessage->handle($order),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        SendWhatsAppNotification::dispatchAfterResponse($notification);

        return $notification;
    }
}
