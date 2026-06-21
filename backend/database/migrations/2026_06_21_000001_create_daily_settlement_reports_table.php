<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_settlement_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->enum('type', ['supplier_purchase', 'distributor_order', 'agent_order', 'all'])->default('all');
            $table->unique(['report_date', 'type']);

            $table->integer('total_orders')->default(0);
            $table->integer('purchase_orders')->default(0);
            $table->integer('distributor_orders')->default(0);
            $table->integer('agent_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('pending_orders')->default(0);

            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('purchase_amount', 15, 2)->default(0);
            $table->decimal('sales_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('unpaid_amount', 15, 2)->default(0);

            $table->decimal('total_income', 15, 2)->default(0);
            $table->decimal('total_expense', 15, 2)->default(0);
            $table->decimal('net_profit', 15, 2)->default(0);

            $table->decimal('cash_income', 15, 2)->default(0);
            $table->decimal('bank_transfer_income', 15, 2)->default(0);
            $table->decimal('alipay_income', 15, 2)->default(0);
            $table->decimal('wechat_income', 15, 2)->default(0);

            $table->text('remark')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['report_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_settlement_reports');
    }
};
