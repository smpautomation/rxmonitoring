<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per physical layer/tray slot inside a chamber (1-20), each
 * optionally holding one lot. Joined to its chamber by a real foreign key
 * instead of matching "Chamber 5" against "Chamber 5 Layer 3" text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamber_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamber_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('layer_no');

            $table->foreignId('product_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lot_no')->nullable();
            $table->unsignedInteger('lot_quantity')->nullable();
            $table->string('rx_type')->nullable(); // e.g. "ML (2nd RX)", "RE-RX (3rd RX)"
            $table->decimal('unit_weight_grams', 10, 3)->nullable(); // snapshot from product_models
            $table->decimal('weight_per_tray_kg', 10, 4)->nullable(); // computed = unit_weight * qty / 1000
            $table->text('remarks')->nullable();

            // The scanned work-order reference used to auto-fill model/lot/qty
            // from the separate inventory system.
            $table->string('work_order_id')->nullable();
            $table->foreignId('authorized_pic_id')->nullable()->constrained('personnel')->nullOnDelete();

            $table->timestamps();

            $table->unique(['chamber_id', 'layer_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamber_layers');
    }
};
