<?php

namespace App\Services\Fonnte;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien tipis untuk Fonnte (WhatsApp gateway). Docs: https://docs.fonnte.com
 */
class FonnteClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.fonnte.token'));
    }

    /**
     * Kirim pesan teks ke satu nomor WhatsApp.
     *
     * @throws FonnteException
     */
    public function send(string $phone, string $message): void
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.fonnte.base_url'), '/'))
                // Fonnte memakai token device langsung di header Authorization (tanpa "Bearer").
                ->withHeaders(['Authorization' => (string) config('services.fonnte.token')])
                ->acceptJson()
                ->timeout(15)
                ->asForm()
                ->post('/send', [
                    'target' => $this->normalizePhone($phone),
                    'message' => $message,
                ]);
        } catch (Throwable $e) {
            throw new FonnteException('Tidak bisa menghubungi Fonnte: '.$e->getMessage(), previous: $e);
        }

        // Fonnte membalas HTTP 200 walau gagal; kegagalan ada di field "status".
        if (! $response->successful() || $response->json('status') !== true) {
            $reason = $response->json('reason') ?? "HTTP {$response->status()}";

            Log::warning('Fonnte send gagal', ['target' => $phone, 'reason' => $reason]);

            throw new FonnteException("Fonnte menolak pesan: {$reason}");
        }
    }

    /** 0812... / +62812... / 62812... => 62812... */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
