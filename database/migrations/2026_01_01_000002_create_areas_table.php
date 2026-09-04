<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the old app's hardcoded InputBox("Enter area:") password gate
 * (ncp2 / ncp3 / ncp8 / glasscloth) with a real, manageable lookup table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. NCP2, NCP3, NCP8, GLASSCLOTH
            $table->string('name');
            $table->unsignedTinyInteger('chamber_count')->default(10);
            $table->unsignedTinyInteger('layer_count')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
