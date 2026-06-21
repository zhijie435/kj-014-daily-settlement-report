<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_settlement_reports', function (Blueprint $table) {
            $table->enum('status', ['draft', 'confirmed', 'audited', 'locked'])
                ->default('draft')
                ->after('type');

            $table->unsignedBigInteger('confirmed_by')->nullable()->after('remark');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');

            $table->unsignedBigInteger('audited_by')->nullable()->after('confirmed_at');
            $table->timestamp('audited_at')->nullable()->after('audited_by');

            $table->index('status');
            $table->index(['report_date', 'type', 'status']);

            $table->foreign('confirmed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('audited_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('daily_settlement_reports', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropForeign(['audited_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['report_date', 'type', 'status']);
            $table->dropColumn([
                'status',
                'confirmed_by',
                'confirmed_at',
                'audited_by',
                'audited_at',
            ]);
        });
    }
};
