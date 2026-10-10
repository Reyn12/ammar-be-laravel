<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('qr_url')->nullable()->after('payment_type');
            $table->timestamp('expires_at')->nullable()->after('paid_at');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->renameColumn('phone_number', 'recipient'); // nomor WA atau email
            $table->string('channel')->default('whatsapp')->after('order_id'); // whatsapp, email
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('channel');
            $table->renameColumn('recipient', 'phone_number');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['qr_url', 'expires_at']);
        });
    }
};
