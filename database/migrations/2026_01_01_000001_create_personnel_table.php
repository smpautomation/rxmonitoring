<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personnel are identified entirely by their badge QR code, in the format:
 *   {prefix};{employee_id}:{name}
 * where prefix "01" = PIC (authorizer) and "00" = operator.
 *
 * The table is upserted on every scan (see App\Services\PersonnelScanner), so
 * it doubles as a live directory of everyone who has ever badged in, without
 * needing a separate HR sync job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('name');
            $table->enum('role', ['pic', 'operator']);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel');
    }
};
