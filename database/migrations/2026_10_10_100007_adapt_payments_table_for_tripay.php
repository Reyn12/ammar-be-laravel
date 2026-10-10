<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment gateway yang dipakai: Tripay (bukan Midtrans).
     * Nama kolom dibuat sesuai istilah Tripay (merchant_ref, reference, dll).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('midtrans_order_id', 'merchant_ref'); // = orders.code
            $table->renameColumn('transaction_id', 'reference'); // referensi transaksi Tripay
            $table->renameColumn('payment_type', 'method'); // kode channel, mis. QRIS
            $table->renameColumn('gross_amount', 'amount');
            $table->renameColumn('transaction_status', 'status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status')->default('unpaid')->change(); // unpaid | paid | expired | failed | refund
            $table->integer('fee_amount')->default(0)->after('amount');
            $table->integer('amount_received')->nullable()->after('fee_amount');
            $table->text('qr_string')->nullable()->after('qr_url');
            $table->text('checkout_url')->nullable()->after('qr_string');
            $table->json('callback_payload')->nullable()->after('expires_at');
            $table->unique('merchant_ref');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['merchant_ref']);
            $table->dropIndex(['reference']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'amount_received', 'qr_string', 'checkout_url', 'callback_payload']);
            $table->string('status')->default('pending')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('merchant_ref', 'midtrans_order_id');
            $table->renameColumn('reference', 'transaction_id');
            $table->renameColumn('method', 'payment_type');
            $table->renameColumn('amount', 'gross_amount');
            $table->renameColumn('status', 'transaction_status');
        });
    }
};
