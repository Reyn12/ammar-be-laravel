<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Services\Firebase\FirebaseTokenVerifier;
use App\Services\Firebase\InvalidFirebaseTokenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login customer lewat Firebase ID token (header Authorization: Bearer ...).
 *
 *   customer.auth           -> wajib login
 *   customer.auth:optional  -> boleh tamu; kalau ada token harus valid
 *
 * Customer dibuat / diperbarui otomatis dari data akun Google.
 */
class AuthenticateCustomer
{
    public function __construct(private readonly FirebaseTokenVerifier $verifier) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            if ($mode === 'optional') {
                return $next($request);
            }

            return response()->json(['message' => 'Silakan masuk dengan Google dulu.'], 401);
        }

        try {
            $profile = $this->verifier->verify($token);
        } catch (InvalidFirebaseTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $customer = Customer::updateOrCreate(
            ['firebase_uid' => $profile['uid']],
            [
                'email' => $profile['email'],
                'name' => $profile['name'],
                'avatar_url' => $profile['picture'],
            ],
        );

        $request->attributes->set('customer', $customer);

        return $next($request);
    }
}
