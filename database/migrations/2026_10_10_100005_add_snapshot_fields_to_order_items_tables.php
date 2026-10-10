<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simpan nama dan harga saat transaksi supaya riwayat tidak ikut berubah
     * kalau produk/addon diedit atau dihapus.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
            $table->integer('price')->default(0)->after('qty');
        });

        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->string('name')->nullable()->after('addon_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'price']);
        });
    }
};
