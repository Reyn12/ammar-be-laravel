<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffTableResource;
use App\Models\Table;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Owner mengelola meja dan QR-nya. Menghapus = mengarsipkan (riwayat pesanan tetap tahu nomor mejanya).
 */
class StaffTableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tables = Table::where('branch_id', $request->user()->branch_id)->orderBy('table_number')->get();

        return ApiResponse::success(StaffTableResource::collection($tables)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['table_number' => ['required', 'integer', 'min:1', 'max:999']]);
        $branchId = $request->user()->branch_id;

        $existing = Table::withTrashed()
            ->where('branch_id', $branchId)
            ->where('table_number', $data['table_number'])
            ->first();

        if ($existing && ! $existing->trashed()) {
            throw ValidationException::withMessages(['table_number' => 'Nomor meja sudah ada.']);
        }

        // Meja yang pernah diarsipkan dihidupkan lagi dengan QR baru (QR lama tidak berlaku).
        if ($existing) {
            $existing->restore();
            $existing->update(['is_active' => true]);
            $existing->regenerateQrToken();
            $table = $existing->refresh();
        } else {
            $table = Table::create(['branch_id' => $branchId, 'table_number' => $data['table_number'], 'is_active' => true]);
        }

        return ApiResponse::success((new StaffTableResource($table))->resolve(), 'Meja ditambahkan.', 201);
    }

    public function update(Request $request, Table $table): JsonResponse
    {
        $this->ensureSameBranch($request, $table);

        $data = $request->validate([
            'table_number' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['table_number'])) {
            $taken = Table::withTrashed()
                ->where('branch_id', $table->branch_id)
                ->where('table_number', $data['table_number'])
                ->whereKeyNot($table->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['table_number' => 'Nomor meja sudah dipakai (termasuk meja yang diarsipkan).']);
            }
        }

        $table->update($data);

        return ApiResponse::success((new StaffTableResource($table->refresh()))->resolve(), 'Meja diperbarui.');
    }

    public function destroy(Request $request, Table $table): JsonResponse
    {
        $this->ensureSameBranch($request, $table);

        $table->delete();

        return ApiResponse::success(null, 'Meja dihapus.');
    }

    /** Token baru; QR lama otomatis tidak berlaku lagi. */
    public function regenerateQr(Request $request, Table $table): JsonResponse
    {
        $this->ensureSameBranch($request, $table);

        $table->regenerateQrToken();

        return ApiResponse::success((new StaffTableResource($table->refresh()))->resolve(), 'QR baru dibuat.');
    }

    private function ensureSameBranch(Request $request, Table $table): void
    {
        abort_if($table->branch_id !== $request->user()->branch_id, 404);
    }
}
