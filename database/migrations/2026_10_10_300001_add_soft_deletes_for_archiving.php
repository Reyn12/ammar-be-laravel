<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data yang dipakai riwayat pesanan tidak boleh benar-benar dihapus (foreign key cascade akan
     * ikut menghapus item pesanan), jadi "hapus" berarti diarsipkan.
     */
    private const TABLES = ['products', 'product_addon_groups', 'product_addons', 'users', 'tables'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropSoftDeletes());
        }
    }
};
