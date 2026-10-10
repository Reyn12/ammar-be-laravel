<?php

namespace App\Services\Firebase;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifikasi ID token Firebase Authentication tanpa service account:
 * cukup tanda tangan (kunci publik Google) + project id.
 * Docs: https://firebase.google.com/docs/auth/admin/verify-id-tokens
 */
class FirebaseTokenVerifier
{
    private const JWK_URL = 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com';

    private const CACHE_KEY = 'firebase.jwk';

    private const CACHE_SECONDS = 3600;

    public function isConfigured(): bool
    {
        return filled(config('services.firebase.project_id'));
    }

    /**
     * @return array{uid: string, email: string, name: string, picture: ?string}
     *
     * @throws InvalidFirebaseTokenException
     */
    public function verify(string $idToken): array
    {
        $projectId = (string) config('services.firebase.project_id');

        if ($projectId === '') {
            throw new InvalidFirebaseTokenException('Login Google belum dikonfigurasi di server.');
        }

        try {
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($this->publicKeys(), 'RS256'));
        } catch (Throwable $e) {
            throw new InvalidFirebaseTokenException('Token login tidak valid atau sudah kedaluwarsa.', previous: $e);
        }

        if (($claims['aud'] ?? null) !== $projectId
            || ($claims['iss'] ?? null) !== "https://securetoken.google.com/{$projectId}"
            || blank($claims['sub'] ?? null)) {
            throw new InvalidFirebaseTokenException('Token login bukan untuk aplikasi ini.');
        }

        // Email dari Google sudah terverifikasi; tolak kalau tidak ada / belum diverifikasi.
        if (blank($claims['email'] ?? null) || ($claims['email_verified'] ?? false) !== true) {
            throw new InvalidFirebaseTokenException('Email akun Google belum terverifikasi.');
        }

        return [
            'uid' => (string) $claims['sub'],
            'email' => mb_strtolower((string) $claims['email']),
            'name' => (string) ($claims['name'] ?? $claims['email']),
            'picture' => $claims['picture'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function publicKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            $response = Http::acceptJson()->timeout(10)->get(self::JWK_URL)->throw();

            return $response->json();
        });
    }
}
