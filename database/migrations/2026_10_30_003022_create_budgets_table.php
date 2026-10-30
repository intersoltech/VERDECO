<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('cost_category_id')->nullable();
            $table->date('period_month'); // e.g. 2026-10-01
            $table->decimal('budgeted_cost', 18, 2)->default(0);
            $table->decimal('budgeted_output_kg', 18, 4)->default(0);
            $table->decimal('budgeted_cost_per_kg', 18, 6)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('cost_category_id')->references('id')->on('cost_categories')->nullOnDelete();

            $table->unique(['tenant_id', 'plant_id', 'cost_category_id', 'period_month'], 'budget_plant_category_month_uq');
            $table->index(['tenant_id', 'plant_id', 'period_month'], 'budget_plant_month_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
