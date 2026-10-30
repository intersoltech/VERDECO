<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_daily_line_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('line_id');
            $table->date('production_date');
            $table->decimal('input_kg', 18, 4)->default(0);
            $table->decimal('output_kg', 18, 4)->default(0);
            $table->decimal('material_cost', 18, 2)->default(0);
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('utility_cost', 18, 2)->default(0);
            $table->decimal('overhead_cost', 18, 2)->default(0);
            $table->decimal('incident_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->decimal('cost_per_kg', 18, 6)->default(0);
            $table->decimal('yield_percent', 8, 4)->default(0);
            $table->integer('batch_count')->default(0);
            $table->integer('downtime_minutes')->default(0);
            $table->decimal('energy_kwh', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('line_id')->references('id')->on('production_lines')->cascadeOnDelete();

            $table->unique(['tenant_id', 'plant_id', 'line_id', 'production_date'], 'fact_daily_line_unique');
            $table->index(['tenant_id', 'plant_id', 'production_date'], 'fact_daily_line_date_idx');
        });

        Schema::create('fact_daily_plant_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->date('production_date');
            $table->decimal('input_kg', 18, 4)->default(0);
            $table->decimal('good_output_kg', 18, 4)->default(0);
            $table->decimal('yield_percent', 8, 4)->default(0);
            $table->decimal('actual_cost', 18, 2)->default(0);
            $table->decimal('standard_cost', 18, 2)->default(0);
            $table->decimal('variance_cost', 18, 2)->default(0);
            $table->decimal('cost_per_kg', 18, 6)->default(0);
            $table->decimal('kwh_per_kg', 18, 6)->default(0);
            $table->decimal('incident_cash_cost', 18, 2)->default(0);
            $table->decimal('incident_opportunity_cost', 18, 2)->default(0);
            $table->integer('open_incident_count')->default(0);
            $table->integer('downtime_minutes')->default(0);
            $table->integer('batch_count')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();

            $table->unique(['tenant_id', 'plant_id', 'production_date'], 'fact_daily_plant_kpi_unique');
            $table->index(['tenant_id', 'production_date'], 'fact_daily_plant_kpi_date_idx');
        });

        Schema::create('fact_monthly_variances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->date('period_month');
            $table->decimal('actual_cost', 18, 2)->default(0);
            $table->decimal('standard_cost', 18, 2)->default(0);
            $table->decimal('total_variance', 18, 2)->default(0);
            $table->decimal('material_price_variance', 18, 2)->default(0);
            $table->decimal('material_usage_variance', 18, 2)->default(0);
            $table->decimal('labor_rate_variance', 18, 2)->default(0);
            $table->decimal('labor_efficiency_variance', 18, 2)->default(0);
            $table->decimal('utility_efficiency_variance', 18, 2)->default(0);
            $table->decimal('overhead_volume_variance', 18, 2)->default(0);
            $table->decimal('incident_variance', 18, 2)->default(0);
            $table->decimal('output_kg', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();

            $table->unique(['tenant_id', 'plant_id', 'period_month'], 'fact_monthly_variance_unique');
        });

        Schema::create('fact_incident_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->date('incident_date');
            $table->string('incident_type', 50);
            $table->string('root_cause_category', 50);
            $table->decimal('cash_cost', 18, 2)->default(0);
            $table->decimal('opportunity_cost', 18, 2)->default(0);
            $table->integer('incident_count')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();

            $table->unique(['tenant_id', 'plant_id', 'incident_date', 'incident_type', 'root_cause_category'], 'fact_incident_summary_unique');
            $table->index(['tenant_id', 'plant_id', 'incident_date'], 'fact_incident_summary_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_incident_summaries');
        Schema::dropIfExists('fact_monthly_variances');
        Schema::dropIfExists('fact_daily_plant_kpis');
        Schema::dropIfExists('fact_daily_line_costs');
    }
};
