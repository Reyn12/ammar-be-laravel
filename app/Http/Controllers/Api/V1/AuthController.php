<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login staf (owner, kasir, koki) untuk aplikasi Flutter. Customer memakai login Google (Firebase).
 */
class AuthController extends Controller
{
    private const ROLE_LABELS = [
        'owner' => 'Pemilik toko',
        'cashier' => 'Kasir cabang',
        'kitchen' => 'Koki / dapur',
    ];

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])->first();

        // Pesan sama untuk username salah / password salah supaya akun tidak bisa ditebak.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => 'Username atau password salah.']);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Akun ini dinonaktifkan. Hubungi pemilik toko.', 403);
        }

        $token = $user->createToken('flutter-app');
        $expiresAt = $token->accessToken->expires_at;

        return ApiResponse::success([
            'access_token' => $token->plainTextToken,
            'refresh_token' => null,
            'token_type' => 'Bearer',
            'expires_in' => $expiresAt ? (int) now()->diffInSeconds($expiresAt) : null,
            'user' => $this->userPayload($user),
        ], 'Login berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Berhasil keluar.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'different:current_password'],
        ], [
            'new_password.different' => 'Password baru harus berbeda dari password lama.',
            'new_password.min' => 'Password baru minimal 6 karakter.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password lama tidak sesuai.']);
        }

        $user->update(['password' => $data['new_password']]);

        // Perangkat lain harus login ulang; perangkat ini tetap masuk.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return ApiResponse::success(null, 'Password berhasil diubah.');
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'branch_id' => $user->branch_id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => null,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'roles' => [[
                'id' => array_search($user->role, array_keys(self::ROLE_LABELS), true) + 1,
                'name' => $user->role,
                'description' => self::ROLE_LABELS[$user->role] ?? $user->role,
            ]],
        ];
    }
}
