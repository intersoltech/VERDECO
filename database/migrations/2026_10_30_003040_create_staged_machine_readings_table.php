<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staged_machine_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('line_id')->nullable();
            $table->unsignedBigInteger('production_batch_id')->nullable();
            $table->string('source', 64)->default('scada');
            $table->string('external_id', 128)->nullable();
            $table->string('reading_type', 32); // meter_reading, production_tick, downtime_event
            $table->string('meter_type', 32)->nullable(); // electricity_kwh, natural_gas_m3, water_m3, nitrogen_m3
            $table->decimal('reading_value', 18, 4)->nullable();
            $table->string('unit', 16)->nullable(); // kwh, kg, lb, minutes, m3
            $table->string('status', 24)->default('pending'); // pending, processed, rejected
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('read_at');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('line_id')->references('id')->on('production_lines')->nullOnDelete();
            $table->foreign('production_batch_id')->references('id')->on('production_batches')->nullOnDelete();

            $table->index(['tenant_id', 'plant_id', 'reading_type'], 'staged_readings_plant_type_idx');
            $table->index(['tenant_id', 'external_id'], 'staged_readings_external_idx');
            $table->index(['status', 'read_at'], 'staged_readings_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staged_machine_readings');
    }
};
