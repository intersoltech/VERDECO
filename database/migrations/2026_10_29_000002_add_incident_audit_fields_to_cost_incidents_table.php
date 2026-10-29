<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_incidents', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('allocation_rule');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('closed_by')->nullable()->after('approved_at');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->unsignedBigInteger('reopened_by')->nullable()->after('closed_at');
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reopened_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cost_incidents', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['reopened_by']);
            $table->dropColumn(['approved_by', 'approved_at', 'closed_by', 'closed_at', 'reopened_by', 'reopened_at']);
        });
    }
};
