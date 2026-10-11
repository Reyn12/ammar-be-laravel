<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori yang dihapus hanya diarsipkan: produk yang sudah diarsipkan masih menunjuk ke
     * kategorinya, dan foreign key cascade akan ikut menghapus riwayat pesanannya.
     */
    public function up(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
