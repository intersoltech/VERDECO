<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // kg, lb, ton, g, kwh, mwh
            $table->string('name', 50);          // Kilogram, US Pound, Metric Ton, etc.
            $table->enum('type', ['mass', 'energy', 'volume', 'time'])->default('mass');
            $table->decimal('conversion_to_base', 18, 8)->default(1.0); // Factor to multiply to convert to base (base mass is kg)
            $table->string('symbol', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
