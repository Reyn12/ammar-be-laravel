<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\Firebase\FcmClient;
use App\Services\Firebase\FcmException;
use App\Services\Firebase\StaffPushNotifier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pendaftaran token FCM perangkat staf untuk push notifikasi pesanan masuk.
 */
class DeviceTokenController extends Controller
{
    /** Daftar / perbarui token. Token yang sama dipindah ke akun yang sedang login (tablet dipakai bergantian). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['sometimes', 'string', 'in:android'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? 'android'],
        );

        return ApiResponse::success(null, 'Perangkat terdaftar.', 201);
    }

    /** Lepas token saat logout supaya akun yang sudah keluar tidak menerima push. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        $request->user()->deviceTokens()->where('token', $data['token'])->delete();

        return ApiResponse::success(null, 'Perangkat dilepas.');
    }

    /** Kirim notifikasi tes ke perangkat milik akun yang login, untuk memastikan push berfungsi. */
    public function test(Request $request, FcmClient $fcm, StaffPushNotifier $notifier): JsonResponse
    {
        if (! $fcm->isConfigured()) {
            return ApiResponse::error('Push belum dikonfigurasi di server (FIREBASE_CREDENTIALS).', 503);
        }

        if (! $request->user()->deviceTokens()->exists()) {
            return ApiResponse::error('Perangkat ini belum terdaftar untuk push. Login ulang dan izinkan notifikasi.', 422);
        }

        try {
            $delivered = $notifier->sendTest($request->user());
        } catch (FcmException $e) {
            report($e);

            return ApiResponse::error('Server gagal menghubungi FCM. Cek log server.', 502);
        }

        if ($delivered === 0) {
            return ApiResponse::error('Token perangkat sudah tidak valid. Login ulang untuk mendaftar lagi.', 422);
        }

        return ApiResponse::success(['delivered' => $delivered], "Notifikasi tes dikirim ke {$delivered} perangkat.");
    }
}
