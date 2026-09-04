<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces oven_list. Old table stored Oven_Capacity and Peak_Temp as loose
 * text; both become real decimals here so the app can do math and validation
 * on them instead of Val()-ing strings at runtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ovens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('oven_no');
            $table->decimal('capacity_kg', 8, 2)->nullable();
            $table->decimal('peak_temp_target_c', 6, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['area_id', 'oven_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ovens');
    }
};
