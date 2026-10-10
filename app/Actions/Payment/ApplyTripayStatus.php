<?php

namespace App\Actions\Payment;

use App\Actions\Kitchen\AssignOrderToBatches;
use App\Actions\Notification\SendOrderReceipt;
use App\Models\Payment;
use App\Models\StaffEvent;
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

    public function __construct(
        private readonly SendOrderReceipt $sendReceipt,
        private readonly AssignOrderToBatches $assignBatches,
    ) {}

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
                $this->assignBatches->handle($order);
            } elseif (in_array($status, ['expired', 'failed'], true)) {
                $order->update(['payment_status' => 'expired', 'status' => 'cancelled']);
            }

            return $payment->refresh();
        });

        // Notifikasi dan struk dikirim setelah transaksi database selesai.
        if ($status === 'paid') {
            StaffEvent::emit(StaffEvent::ORDER_PAID, $updated->order);
            $this->sendReceipt->handle($updated->order);
        } elseif (in_array($status, ['expired', 'failed'], true)) {
            StaffEvent::emit(StaffEvent::ORDER_CANCELLED, $updated->order);
        }

        return $updated;
    }
}
