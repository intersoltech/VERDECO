<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downtime_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('line_id');
            $table->unsignedBigInteger('cost_incident_id')->nullable();
            $table->enum('downtime_type', ['planned', 'unplanned'])->default('unplanned');
            $table->string('reason_code'); // e.g. mechanical_failure, screen_pack_change, die_cleaning, feed_starvation, electrical, startup
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->decimal('lost_production_kg', 18, 4)->default(0);
            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('line_id')->references('id')->on('production_lines')->cascadeOnDelete();
            $table->foreign('cost_incident_id')->references('id')->on('cost_incidents')->nullOnDelete();
            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'plant_id', 'start_at'], 'downtime_tenant_plant_start_idx');
            $table->index(['tenant_id', 'plant_id', 'line_id', 'start_at'], 'downtime_line_start_idx');
            $table->index(['tenant_id', 'plant_id', 'reason_code'], 'downtime_reason_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downtime_events');
    }
};
