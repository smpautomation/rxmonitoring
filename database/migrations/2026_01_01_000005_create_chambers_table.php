<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This is the single biggest structural fix over the legacy `rx_sheet` table.
 *
 * In the old schema, every one of the up-to-20 layer rows for a chamber
 * carried its own copy of Start_Temperature, Loaded_By, Peak_Temp_Time,
 * Stop_Temperature, Cooling_Start_Time, Cooling_End_Time, Checked_By, etc.
 * Setting the start temperature meant an UPDATE ... WHERE Chamber_No = 'X'
 * OR Chamber_No LIKE 'X %' that rewrote all 20 rows at once - fragile string
 * matching standing in for a real relationship, and 20x redundant writes for
 * one fact.
 *
 * Here, the oven-cycle fields live exactly once, on the chamber itself.
 * Per-lot detail moves to `chamber_layers`, joined by a real foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chambers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('chamber_number'); // 1-10, label is "Chamber 05"
            $table->date('shift_date');
            $table->enum('status', ['open', 'closed'])->default('open');

            // Location Parameter step
            $table->foreignId('authorized_by_id')->nullable()->constrained('personnel')->nullOnDelete();

            // RX Oven Setup step
            $table->foreignId('oven_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('oven_capacity_kg', 8, 2)->nullable(); // snapshot at assignment time
            $table->decimal('peak_temp_target_c', 6, 2)->nullable(); // snapshot at assignment time

            // Computed from chamber_layers, cached for fast list rendering
            $table->decimal('total_weight_kg', 10, 4)->default(0);
            $table->enum('weight_status', ['unset', 'ok', 'overweight'])->default('unset');

            // Before RX (after loading)
            $table->decimal('start_temperature_c', 6, 2)->nullable();
            $table->foreignId('loaded_by_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->timestamp('start_time')->nullable();

            // Peak temperature reached
            $table->foreignId('peak_checked_by_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->timestamp('peak_temp_time')->nullable();

            // After RX (before unloading)
            $table->decimal('stop_temperature_c', 6, 2)->nullable();
            $table->foreignId('unloaded_by_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->timestamp('stop_time')->nullable();

            // Cooling
            $table->timestamp('cooling_start_time')->nullable();
            $table->timestamp('cooling_end_time')->nullable();

            // Confirmation / close - now a PIC badge scan instead of a
            // month-digit password
            $table->foreignId('closed_by_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->timestamp('closed_time')->nullable();

            $table->timestamps();

            // Enforced in the application layer too (MySQL can't express a
            // partial/conditional unique index), but this speeds up the
            // "is this chamber number already open" lookup.
            $table->index(['area_id', 'chamber_number', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chambers');
    }
};
