<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_costs', function (Blueprint $table) {
            $table->string('status', 24)->default('approved')->after('product_grade_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('overhead_rate');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('created_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('submitted_by');
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'plant_id', 'status'], 'standard_costs_plant_status_idx');
        });

        Schema::table('cost_incidents', function (Blueprint $table) {
            $table->unsignedBigInteger('reported_by_user_id')->nullable()->after('owner');
            $table->foreign('reported_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('closed_by')->nullable()->after('status');
            $table->dateTime('closed_at')->nullable()->after('closed_by');
            $table->unsignedBigInteger('reopened_by')->nullable()->after('closed_at');
            $table->dateTime('reopened_at')->nullable()->after('reopened_by');
            $table->text('adjustment_reason')->nullable()->after('reopened_at');

            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reopened_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['reopened_by']);
            $table->dropColumn(['closed_by', 'closed_at', 'reopened_by', 'reopened_at', 'adjustment_reason']);
        });

        Schema::table('cost_incidents', function (Blueprint $table) {
            $table->dropForeign(['reported_by_user_id']);
            $table->dropColumn(['reported_by_user_id']);
        });

        Schema::table('standard_costs', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex('standard_costs_plant_status_idx');
            $table->dropColumn(['status', 'created_by', 'submitted_by', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
