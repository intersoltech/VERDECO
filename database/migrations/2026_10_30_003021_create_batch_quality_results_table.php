<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_quality_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('production_batch_id');
            $table->timestamp('tested_at');
            $table->string('sample_identifier')->nullable();
            $table->decimal('intrinsic_viscosity', 8, 4)->nullable(); // IV dl/g
            $table->decimal('acetaldehyde_ppm', 8, 4)->nullable();    // AA ppm
            $table->decimal('color_l', 8, 4)->nullable();             // L* (brightness 0-100)
            $table->decimal('color_a', 8, 4)->nullable();             // a* (green-red)
            $table->decimal('color_b', 8, 4)->nullable();             // b* (blue-yellow)
            $table->decimal('moisture_ppm', 10, 4)->nullable();
            $table->integer('black_specks_count')->default(0);
            $table->decimal('fines_percent', 8, 4)->default(0);
            $table->enum('status', ['pass', 'fail', 'hold', 'quarantined'])->default('pass');
            $table->string('hold_reason')->nullable();
            $table->text('inspector_notes')->nullable();
            $table->unsignedBigInteger('inspected_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('production_batch_id')->references('id')->on('production_batches')->cascadeOnDelete();
            $table->foreign('inspected_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'plant_id', 'production_batch_id'], 'quality_batch_scope_idx');
            $table->index(['tenant_id', 'plant_id', 'status'], 'quality_status_scope_idx');
            $table->index(['tenant_id', 'plant_id', 'tested_at'], 'quality_tested_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_quality_results');
    }
};
