<?php

namespace App\Actions\Payment;

use App\Actions\Notification\SendOrderReceipt;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ApplyTripayStatus
{
    /** Status Tripay (huruf besar) => status payments. */
    private const STATUS_MAP = [
        'UNPAID' => 'unpaid',
        'PAID' => 'paid',
        'EXPIRED' => 'expired',
        'FAILED' => 'failed',
        'REFUND' => 'refund',
    ];

    public function __construct(private readonly SendOrderReceipt $sendReceipt) {}

    /**
     * Terapkan status dari Tripay (callback atau cek manual) ke payment dan order.
     * Aman dipanggil berulang: pembayaran yang sudah lunas tidak diturunkan lagi.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Payment $payment, array $data): Payment
    {
        $status = self::STATUS_MAP[strtoupper((string) ($data['status'] ?? ''))] ?? null;

        if ($status === null || $payment->status === 'paid') {
            return $payment;
        }

        $updated = DB::transaction(function () use ($payment, $status, $data) {
            $payment->update([
                'status' => $status,
                'amount_received' => $data['amount_received'] ?? $payment->amount_received,
                'paid_at' => $status === 'paid' ? now() : $payment->paid_at,
                'callback_payload' => $data,
            ]);

            $order = $payment->order;

            if ($status === 'paid') {
                $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
            } elseif (in_array($status, ['expired', 'failed'], true)) {
                $order->update(['payment_status' => 'expired', 'status' => 'cancelled']);
            }

            return $payment->refresh();
        });

        // Struk WhatsApp dikirim setelah transaksi database selesai.
        if ($status === 'paid') {
            $this->sendReceipt->handle($updated->order);
        }

        return $updated;
    }
}
