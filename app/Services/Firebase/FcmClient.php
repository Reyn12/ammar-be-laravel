<?php

namespace App\Services\Firebase;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien tipis untuk Firebase Cloud Messaging HTTP v1, tanpa SDK tambahan.
 * Memakai service account (file JSON) untuk menukar JWT dengan access token OAuth.
 * Docs: https://firebase.google.com/docs/cloud-messaging/send-message
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'firebase.fcm_access_token';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Kirim satu notifikasi ke satu perangkat.
     *
     * @param  array<string, scalar|null>  $data
     * @return bool false kalau token perangkat sudah tidak valid (hapus dari database)
     *
     * @throws FcmException
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->credentials() ?? throw new FcmException('Kredensial Firebase belum dikonfigurasi.');
        $projectId = $credentials['project_id'] ?? config('services.firebase.project_id');

        try {
            $response = Http::withToken($this->accessToken($credentials))
                ->acceptJson()
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => ['title' => $title, 'body' => $body],
                        // FCM hanya menerima nilai string di data.
                        'data' => array_map(fn ($value) => (string) $value, $data),
                        'android' => [
                            'priority' => 'HIGH',
                            // Pesanan yang tertunda lama tidak berguna lagi bagi kasir.
                            'ttl' => '300s',
                            'notification' => [
                                'channel_id' => config('services.firebase.android_channel'),
                                'sound' => config('services.firebase.android_sound'),
                            ],
                        ],
                    ],
                ]);
        } catch (Throwable $e) {
            throw new FcmException('Tidak bisa menghubungi FCM: '.$e->getMessage(), previous: $e);
        }

        if ($response->successful()) {
            return true;
        }

        // Token kedaluwarsa / app dihapus: FCM membalas 404 UNREGISTERED atau 400 INVALID_ARGUMENT.
        $status = $response->json('error.status');
        if (in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            return false;
        }

        throw new FcmException("FCM menolak pesan: HTTP {$response->status()} {$status}");
    }

    /**
     * @param  array<string, mixed>  $credentials
     *
     * @throws FcmException
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            try {
                $response = Http::asForm()->timeout(10)->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
            } catch (Throwable $e) {
                throw new FcmException('Tidak bisa meminta access token Google: '.$e->getMessage(), previous: $e);
            }

            if (! $response->successful() || blank($response->json('access_token'))) {
                throw new FcmException('Google menolak kredensial Firebase: HTTP '.$response->status());
            }

            return $response->json('access_token');
        });
    }

    /** @return array<string, mixed>|null */
    private function credentials(): ?array
    {
        $path = config('services.firebase.credentials');

        if (blank($path) || ! is_readable($path)) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials) || blank($credentials['client_email'] ?? null) || blank($credentials['private_key'] ?? null)) {
            return null;
        }

        return $credentials;
    }
}
