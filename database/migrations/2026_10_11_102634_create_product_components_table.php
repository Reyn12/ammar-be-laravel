<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi paket: produk paket terdiri dari beberapa produk biasa. Saat pesanan dibuat,
     * paket dipecah menjadi item dapur per komponen supaya bisa di-batch.
     */
    public function up(): void
    {
        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
            // Pilihan paket (mis. bakar/goreng) dan catatan customer diteruskan ke komponen ini.
            $table->boolean('receives_options')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
