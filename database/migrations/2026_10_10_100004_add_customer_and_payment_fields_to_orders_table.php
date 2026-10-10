<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id'); // AMR-20261010-123
            $table->foreignId('customer_id')->nullable()->after('table_id')
                ->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable()->after('branch_id');
            $table->string('customer_phone')->nullable()->index()->after('customer_name');
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->integer('tax_amount')->default(0)->after('total_amount');
            $table->integer('cash_received')->nullable()->after('tax_amount');
            $table->foreignId('confirmed_by')->nullable()->after('cash_received')
                ->constrained('users')->nullOnDelete(); // kasir yang approve tunai
            $table->timestamp('paid_at')->nullable()->after('confirmed_by');
            $table->timestamp('expires_at')->nullable()->after('paid_at'); // batas bayar QRIS
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['confirmed_by']);
            $table->dropUnique(['code']);
            $table->dropIndex(['customer_phone']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'customer_id',
                'customer_name',
                'customer_phone',
                'customer_email',
                'tax_amount',
                'cash_received',
                'confirmed_by',
                'paid_at',
                'expires_at',
            ]);
        });
    }
};
