<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_period_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('cost_period_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_email');
            $table->string('event');
            $table->string('from_status');
            $table->string('to_status');
            $table->timestamp('occurred_at');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->foreign('cost_period_id')->references('id')->on('cost_periods')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'plant_id', 'cost_period_id', 'occurred_at'], 'cost_period_events_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_period_events');
    }
};