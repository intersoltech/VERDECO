<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->index(['plant_id', 'status', 'batch_date'], 'batches_plant_status_date_idx');
            $table->index(['line_id', 'status'], 'batches_line_status_idx');
        });

        Schema::table('cost_incidents', function (Blueprint $table) {
            $table->index(['plant_id', 'status', 'occurred_at'], 'incidents_plant_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cost_incidents', function (Blueprint $table) {
            $table->dropIndex('incidents_plant_status_date_idx');
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropIndex('batches_plant_status_date_idx');
            $table->dropIndex('batches_line_status_idx');
        });
    }
};
