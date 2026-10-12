<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Batch tidak lagi dibatasi jumlah nota, jadi status "full" dihapus.
     */
    public function up(): void
    {
        DB::table('batches')->where('status', 'full')->update(['status' => 'open']);
    }

    public function down(): void
    {
        // Status full tidak bisa dipulihkan: batas nota sudah dihapus.
    }
};
