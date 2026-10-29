<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country_code', 2)->default('US');
            $table->timestamps();
        });

        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            $table->string('timezone')->default('America/Chicago');
            $table->string('base_currency', 3)->default('USD');
            $table->enum('costing_mode', ['batch', 'daily', 'both'])->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('plant_user', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->primary(['plant_id', 'user_id']);
            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('rated_kg_per_hr', 18, 4)->default(0);
            $table->decimal('rated_kw', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('country_code', 2)->default('US');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category');
            $table->string('unit')->default('kg');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('material_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('material_id');
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id', 'material_id', 'effective_from'], 'material_prices_lookup_idx');
        });

        Schema::create('product_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->string('grade_type');
            $table->decimal('iv_min', 8, 4)->nullable();
            $table->decimal('iv_max', 8, 4)->nullable();
            $table->decimal('aa_max', 8, 4)->nullable();
            $table->decimal('color_l_min', 8, 4)->nullable();
            $table->decimal('color_l_max', 8, 4)->nullable();
            $table->decimal('color_b_min', 8, 4)->nullable();
            $table->decimal('color_b_max', 8, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('cost_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('kind', ['direct', 'indirect'])->default('direct');
            $table->enum('type', ['fixed', 'variable'])->default('variable');
            $table->string('allocation_basis')->default('per_kg');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('standard_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('line_id')->nullable();
            $table->unsignedBigInteger('product_grade_id')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->decimal('standard_yield_percent', 8, 4)->default(0);
            $table->decimal('kg_per_hour', 18, 4)->default(0);
            $table->decimal('kwh_per_kg', 18, 6)->default(0);
            $table->decimal('labor_hours_per_ton', 18, 6)->default(0);
            $table->decimal('material_usage_kg_per_kg', 18, 6)->default(0);
            $table->decimal('overhead_rate', 18, 6)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('line_id')->references('id')->on('production_lines')->nullOnDelete();
            $table->foreign('product_grade_id')->references('id')->on('product_grades')->nullOnDelete();
            $table->index(['tenant_id', 'plant_id', 'effective_from']);
        });

        Schema::create('cost_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('line_id')->nullable();
            $table->string('incident_type');
            $table->string('severity');
            $table->string('root_cause_category');
            $table->text('description')->nullable();
            $table->string('owner')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('occurred_at');
            $table->decimal('cash_cost', 18, 2)->default(0);
            $table->decimal('opportunity_cost', 18, 2)->default(0);
            $table->string('allocation_rule')->default('by_kg');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('line_id')->references('id')->on('production_lines')->nullOnDelete();
            $table->index(['tenant_id', 'plant_id', 'occurred_at']);
        });

        Schema::create('cost_incident_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id');
            $table->string('line_type');
            $table->text('description')->nullable();
            $table->decimal('extra_labor_hours', 18, 4)->default(0);
            $table->decimal('extra_labor_rate', 18, 6)->default(0);
            $table->decimal('overtime_multiplier', 8, 4)->default(1);
            $table->integer('headcount')->default(0);
            $table->decimal('parts_cost', 18, 2)->default(0);
            $table->decimal('contractor_cost', 18, 2)->default(0);
            $table->decimal('material_loss_kg', 18, 4)->default(0);
            $table->decimal('material_loss_cost', 18, 2)->default(0);
            $table->decimal('rework_cost', 18, 2)->default(0);
            $table->decimal('rush_freight_cost', 18, 2)->default(0);
            $table->decimal('utility_spike_cost', 18, 2)->default(0);
            $table->decimal('quality_hold_cost', 18, 2)->default(0);
            $table->decimal('other_cost', 18, 2)->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('cost_incidents')->cascadeOnDelete();
            $table->index(['incident_id', 'line_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_incident_lines');
        Schema::dropIfExists('cost_incidents');
        Schema::dropIfExists('standard_costs');
        Schema::dropIfExists('cost_categories');
        Schema::dropIfExists('product_grades');
        Schema::dropIfExists('material_prices');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('production_lines');
        Schema::dropIfExists('plant_user');
        Schema::dropIfExists('plants');
        Schema::dropIfExists('organizations');
    }
};
