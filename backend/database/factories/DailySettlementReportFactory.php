<?php

namespace Database\Factories;

use App\Models\DailySettlementReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailySettlementReportFactory extends Factory
{
    protected $model = DailySettlementReport::class;

    public function definition(): array
    {
        static $dateOffset = 0;

        return [
            'report_date' => now()->subDays($dateOffset++)->format('Y-m-d'),
            'type' => DailySettlementReport::TYPE_ALL,
            'status' => DailySettlementReport::STATUS_DRAFT,
            'total_orders' => $this->faker->numberBetween(1, 100),
            'purchase_orders' => $this->faker->numberBetween(0, 50),
            'distributor_orders' => $this->faker->numberBetween(0, 50),
            'agent_orders' => $this->faker->numberBetween(0, 50),
            'completed_orders' => $this->faker->numberBetween(0, 100),
            'pending_orders' => $this->faker->numberBetween(0, 50),
            'total_amount' => $this->faker->randomFloat(2, 100, 10000),
            'purchase_amount' => $this->faker->randomFloat(2, 50, 5000),
            'sales_amount' => $this->faker->randomFloat(2, 50, 5000),
            'paid_amount' => $this->faker->randomFloat(2, 50, 8000),
            'unpaid_amount' => $this->faker->randomFloat(2, 0, 3000),
            'total_income' => $this->faker->randomFloat(2, 100, 8000),
            'total_expense' => $this->faker->randomFloat(2, 50, 4000),
            'net_profit' => $this->faker->randomFloat(2, 100, 5000),
            'cash_income' => $this->faker->randomFloat(2, 10, 2000),
            'bank_transfer_income' => $this->faker->randomFloat(2, 10, 3000),
            'alipay_income' => $this->faker->randomFloat(2, 10, 2000),
            'wechat_income' => $this->faker->randomFloat(2, 10, 2000),
            'remark' => $this->faker->optional()->text(200),
            'generated_by' => null,
            'generated_at' => now(),
        ];
    }
}
