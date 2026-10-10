<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('table_number');
            $table->boolean('is_active')->default(true)->after('qr_token');
        });

        DB::table('tables')->whereNull('qr_token')->get()->each(
            fn ($row) => DB::table('tables')
                ->where('id', $row->id)
                ->update(['qr_token' => Str::random(32)])
        );

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('qr_code_url');
            $table->unique(['branch_id', 'table_number']);
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'table_number']);
            $table->dropUnique(['qr_token']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->string('qr_code_url')->nullable();
            $table->dropColumn(['qr_token', 'is_active']);
        });
    }
};
