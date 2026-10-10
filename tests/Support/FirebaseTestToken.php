<?php

namespace Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;

/**
 * Membuat ID token Firebase palsu yang ditandatangani kunci RSA sementara,
 * lalu menaruh kunci publiknya di cache sebagai pengganti JWK Google.
 */
class FirebaseTestToken
{
    public const PROJECT_ID = 'ammar-test';

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function make(array $claims = [], ?string $signWithOtherKey = null): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($key);

        Cache::put('firebase.jwk', ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => 'test-key',
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ]]]);

        config()->set('services.firebase.project_id', self::PROJECT_ID);

        if ($signWithOtherKey) {
            openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048]), $privatePem);
        } else {
            openssl_pkey_export($key, $privatePem);
        }

        $payload = array_merge([
            'iss' => 'https://securetoken.google.com/'.self::PROJECT_ID,
            'aud' => self::PROJECT_ID,
            'sub' => 'firebase-uid-1',
            'email' => 'Budi@Example.com',
            'email_verified' => true,
            'name' => 'Budi Santoso',
            'picture' => 'https://example.com/budi.png',
            'iat' => time() - 10,
            'exp' => time() + 3600,
        ], $claims);

        return JWT::encode($payload, $privatePem, 'RS256', 'test-key');
    }
}
