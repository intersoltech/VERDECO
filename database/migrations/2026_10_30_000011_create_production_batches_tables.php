<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_batches')) {
            Schema::create('production_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('plant_id');
                $table->unsignedBigInteger('line_id')->nullable();
                $table->unsignedBigInteger('product_grade_id')->nullable();
                $table->string('batch_number');
                $table->date('batch_date');
                $table->string('status')->default('draft');
                $table->decimal('input_kg', 18, 4)->default(0);
                $table->decimal('good_output_kg', 18, 4)->default(0);
                $table->decimal('recovery_credit', 18, 2)->default(0);
                $table->decimal('labor_regular_rate', 18, 6)->default(0);
                $table->decimal('labor_overtime_rate', 18, 6)->default(0);
                $table->decimal('labor_regular_hours', 18, 4)->default(0);
                $table->decimal('labor_overtime_hours', 18, 4)->default(0);
                $table->decimal('labor_overtime_multiplier', 8, 4)->default(1);
                $table->decimal('utility_kwh', 18, 4)->default(0);
                $table->decimal('utility_rate', 18, 6)->default(0);
                $table->decimal('overhead_pool', 18, 2)->default(0);
                $table->string('overhead_basis')->default('per_kg');
                $table->decimal('incident_cost', 18, 2)->default(0);
                $table->string('incident_allocation_rule')->default('direct_tag');
                $table->decimal('material_cost', 18, 2)->default(0);
                $table->decimal('labor_cost', 18, 2)->default(0);
                $table->decimal('utility_cost', 18, 2)->default(0);
                $table->decimal('overhead_cost', 18, 2)->default(0);
                $table->decimal('total_cost', 18, 2)->default(0);
                $table->decimal('net_cost', 18, 2)->default(0);
                $table->decimal('yield_percent', 8, 4)->default(0);
                $table->decimal('cost_per_kg', 18, 6)->default(0);
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
                $table->foreign('line_id')->references('id')->on('production_lines')->nullOnDelete();
                $table->foreign('product_grade_id')->references('id')->on('product_grades')->nullOnDelete();
                $table->unique(['tenant_id', 'plant_id', 'batch_number']);
                $table->index(['tenant_id', 'plant_id', 'batch_date']);
            });
        }

        if (Schema::hasTable('production_batch_materials')) {
            Schema::table('production_batch_materials', function (Blueprint $table) {
                $table->index(['tenant_id', 'plant_id', 'production_batch_id'], 'prod_batch_materials_scope_idx');
            });

            return;
        }

        Schema::create('production_batch_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('production_batch_id');
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('material_name');
            $table->decimal('kg', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('production_batch_id')->references('id')->on('production_batches')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('materials')->nullOnDelete();
            $table->index(['tenant_id', 'plant_id', 'production_batch_id'], 'prod_batch_materials_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_batch_materials');
        Schema::dropIfExists('production_batches');
    }
};
