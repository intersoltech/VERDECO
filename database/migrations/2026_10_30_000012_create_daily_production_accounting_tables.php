<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_production_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->date('production_date');
            $table->string('status')->default('draft');
            $table->decimal('batch_total_cost', 18, 2)->default(0);
            $table->decimal('daily_expense_total', 18, 2)->default(0);
            $table->decimal('incident_total_cost', 18, 2)->default(0);
            $table->decimal('actual_total_cost', 18, 2)->default(0);
            $table->decimal('output_kg', 18, 4)->default(0);
            $table->decimal('cost_per_kg', 18, 6)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'plant_id', 'production_date'], 'daily_prod_summary_scope_date_uq');
            $table->index(['tenant_id', 'plant_id', 'status'], 'daily_prod_summary_scope_status_idx');
        });

        Schema::create('daily_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('daily_production_summary_id');
            $table->string('category');
            $table->string('description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency_code', 3)->default('USD');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('daily_production_summary_id')->references('id')->on('daily_production_summaries')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id', 'daily_production_summary_id'], 'daily_expense_scope_idx');
        });

        Schema::create('daily_production_batch', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('daily_production_summary_id');
            $table->unsignedBigInteger('production_batch_id');
            $table->timestamps();

            $table->primary(['daily_production_summary_id', 'production_batch_id'], 'daily_prod_batch_pk');
            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('daily_production_summary_id', 'daily_prod_batch_summary_fk')->references('id')->on('daily_production_summaries')->cascadeOnDelete();
            $table->foreign('production_batch_id', 'daily_prod_batch_batch_fk')->references('id')->on('production_batches')->cascadeOnDelete();
            $table->unique('production_batch_id', 'daily_prod_batch_batch_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_production_batch');
        Schema::dropIfExists('daily_expenses');
        Schema::dropIfExists('daily_production_summaries');
    }
};
