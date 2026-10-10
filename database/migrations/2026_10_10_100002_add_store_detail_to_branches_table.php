<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->text('description')->nullable()->after('address');
            $table->string('phone')->nullable()->after('description');
            $table->string('image_url')->nullable()->after('phone');
        });

        Schema::create('branch_opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Senin ... 7 = Minggu
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_opening_hours');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['description', 'phone', 'image_url']);
        });
    }
};
