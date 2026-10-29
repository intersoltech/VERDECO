<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_periods', function (Blueprint $table) {
            $table->unsignedBigInteger('reopened_by')->nullable()->after('closed_at');
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->foreign('reopened_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cost_periods', function (Blueprint $table) {
            $table->dropForeign(['reopened_by']);
            $table->dropColumn(['reopened_by', 'reopened_at']);
        });
    }
};