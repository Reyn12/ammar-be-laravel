<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffUserResource;
use App\Models\Shift;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner mengelola akun kasir dan koki. Akun owner tidak bisa dibuat/diubah/dihapus lewat sini.
 */
class StaffUserController extends Controller
{
    private const MANAGEABLE_ROLES = ['cashier', 'kitchen'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['role' => ['nullable', Rule::in(self::MANAGEABLE_ROLES)]]);

        $users = User::where('branch_id', $request->user()->branch_id)
            ->whereIn('role', $request->filled('role') ? [$request->query('role')] : self::MANAGEABLE_ROLES)
            ->orderBy('name')
            ->get();

        return ApiResponse::success(StaffUserResource::collection($users)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'role' => ['required', Rule::in(self::MANAGEABLE_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->messages());

        $user = User::create($data + ['branch_id' => $request->user()->branch_id, 'is_active' => true]);

        return ApiResponse::success((new StaffUserResource($user))->resolve(), 'Akun dibuat.', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);

        $data = $request->validate([
            'username' => ['sometimes', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->messages());

        // Password kosong = tidak diganti.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        // Password diganti atau akun dinonaktifkan: sesi yang sedang berjalan harus berakhir.
        if (isset($data['password']) || ($data['is_active'] ?? true) === false) {
            $user->tokens()->delete();
        }

        return ApiResponse::success((new StaffUserResource($user->refresh()))->resolve(), 'Akun diperbarui.');
    }

    /** Diarsipkan supaya riwayat shift dan pesanan tetap mencatat nama kasirnya. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($request, $user);

        if (Shift::where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['user' => 'Akun ini masih punya shift aktif. Tutup shift-nya dulu.']);
        }

        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, 'Akun dihapus.');
    }

    private function ensureManageable(Request $request, User $user): void
    {
        abort_if($user->branch_id !== $request->user()->branch_id, 404);
        abort_unless(in_array($user->role, self::MANAGEABLE_ROLES, true), 403, 'Akun ini tidak bisa diubah dari sini.');
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'username.unique' => 'Username sudah dipakai.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, strip, dan garis bawah.',
            'password.min' => 'Password minimal 6 karakter.',
        ];
    }
}
