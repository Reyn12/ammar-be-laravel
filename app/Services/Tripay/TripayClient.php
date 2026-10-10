<?php

namespace App\Services\Tripay;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien tipis untuk Tripay (closed payment). Docs: https://tripay.co.id/developer
 */
class TripayClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.tripay.merchant_code'))
            && filled(config('services.tripay.api_key'))
            && filled(config('services.tripay.private_key'));
    }

    /**
     * Buat transaksi QRIS untuk satu order.
     *
     * @return array<string, mixed> field "data" dari response Tripay
     *
     * @throws TripayException
     */
    public function createTransaction(Order $order): array
    {
        $amount = $order->total_amount;
        $expiredAt = ($order->expires_at ?? now()->addMinutes(config('services.tripay.expiry_minutes')))->timestamp;

        $payload = [
            'method' => config('services.tripay.payment_method'),
            'merchant_ref' => $order->code,
            'amount' => $amount,
            'customer_name' => $order->customer_name,
            // Tripay mewajibkan email; pakai alamat cadangan kalau customer tidak mengisi.
            'customer_email' => $order->customer_email ?: config('mail.from.address'),
            'customer_phone' => $order->customer_phone,
            'order_items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'price' => $item->price + $item->addons->sum('price'),
                'quantity' => $item->qty,
            ])->values()->all(),
            'return_url' => config('services.tripay.return_url'),
            'expired_time' => $expiredAt,
            'signature' => $this->signature($order->code, $amount),
        ];

        $response = $this->request()->asForm()->post('/transaction/create', $payload);

        if (! $response->successful() || ! $response->json('success')) {
            Log::warning('Tripay create transaction gagal', [
                'order' => $order->code,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new TripayException($response->json('message') ?: 'Gagal membuat transaksi pembayaran.');
        }

        return $response->json('data');
    }

    /**
     * Status terbaru sebuah transaksi (dipakai kalau callback belum masuk, mis. di lokal).
     *
     * @return array<string, mixed>|null
     */
    public function transactionDetail(string $reference): ?array
    {
        try {
            $response = $this->request()->get('/transaction/detail', ['reference' => $reference]);
        } catch (Throwable $e) {
            Log::warning('Tripay transaction detail gagal', ['reference' => $reference, 'error' => $e->getMessage()]);

            return null;
        }

        return $response->successful() && $response->json('success')
            ? $response->json('data')
            : null;
    }

    /** Signature callback = HMAC-SHA256 dari raw body memakai private key. */
    public function isValidCallbackSignature(string $rawBody, ?string $signature): bool
    {
        if (! $this->isConfigured() || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, (string) config('services.tripay.private_key'));

        return hash_equals($expected, $signature);
    }

    /** Signature transaksi = HMAC-SHA256(merchant_code + merchant_ref + amount, private key). */
    private function signature(string $merchantRef, int $amount): string
    {
        return hash_hmac(
            'sha256',
            config('services.tripay.merchant_code').$merchantRef.$amount,
            (string) config('services.tripay.private_key'),
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.tripay.base_url'), '/'))
            ->withToken((string) config('services.tripay.api_key'))
            ->acceptJson()
            ->timeout(15);
    }
}
