<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    /** Validasi token dari QR meja dan kembalikan nomor mejanya. */
    public function show(string $token): JsonResponse
    {
        $table = Table::where('qr_token', $token)->where('is_active', true)->firstOrFail();

        return response()->json(['data' => [
            'id' => $table->id,
            'branch_id' => $table->branch_id,
            'table_number' => $table->table_number,
        ]]);
    }
}
