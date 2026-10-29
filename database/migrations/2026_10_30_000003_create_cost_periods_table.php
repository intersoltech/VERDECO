<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->enum('status', ['open', 'approved', 'closed'])->default('open');
            $table->decimal('actual_cost', 18, 2)->default(0);
            $table->decimal('standard_cost', 18, 2)->default(0);
            $table->decimal('variance_cost', 18, 2)->default(0);
            $table->decimal('output_kg', 18, 4)->default(0);
            $table->text('comments')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'plant_id', 'period_start']);
            $table->index(['tenant_id', 'plant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_periods');
    }
};
