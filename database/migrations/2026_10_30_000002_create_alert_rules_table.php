<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('severity')->default('medium');
            $table->boolean('enabled')->default(true);
            $table->decimal('threshold_value', 18, 2)->default(0);
            $table->string('comparison_operator')->default('gt');
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->text('message_template')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
            $table->index(['tenant_id', 'plant_id', 'enabled']);
            $table->index(['tenant_id', 'plant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
