<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces model_list. Checked_By / Last_Updated_By become real personnel
 * references so the app can show "who verified this weight" instead of a
 * loose typed name that can't be cross-referenced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('model_name');
            $table->decimal('unit_weight_grams', 10, 3);
            $table->foreignId('checked_by_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->timestamps();

            $table->unique(['area_id', 'model_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_models');
    }
};
