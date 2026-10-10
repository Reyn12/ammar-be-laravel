<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antrean event untuk SSE (kasir & dapur). Klien yang tersambung kembali
     * bisa melanjutkan dari id terakhir lewat header Last-Event-ID.
     */
    public function up(): void
    {
        Schema::create('staff_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // order.created | order.paid | order.cancelled | kitchen.updated
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['branch_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_events');
    }
};
