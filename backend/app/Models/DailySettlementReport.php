<?php

namespace App\Models;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class DailySettlementReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'report_date', 'type',
        'total_orders', 'purchase_orders', 'distributor_orders', 'agent_orders',
        'completed_orders', 'pending_orders',
        'total_amount', 'purchase_amount', 'sales_amount', 'paid_amount', 'unpaid_amount',
        'total_income', 'total_expense', 'net_profit',
        'cash_income', 'bank_transfer_income', 'alipay_income', 'wechat_income',
        'remark', 'generated_by', 'generated_at'
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'generated_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'purchase_amount' => 'decimal:2',
            'sales_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'unpaid_amount' => 'decimal:2',
            'total_income' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'cash_income' => 'decimal:2',
            'bank_transfer_income' => 'decimal:2',
            'alipay_income' => 'decimal:2',
            'wechat_income' => 'decimal:2',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public static function generateForDate($date, $type = 'all', $userId = null): self
    {
        $reportDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
        $startDate = \Carbon\Carbon::parse($date)->startOfDay();
        $endDate = \Carbon\Carbon::parse($date)->endOfDay();

        $report = self::withTrashed()
            ->whereDate('report_date', $reportDate)
            ->where('type', $type)
            ->first();

        if (!$report) {
            $report = new self(['report_date' => $reportDate, 'type' => $type]);
        } elseif ($report->trashed()) {
            $report->restore();
        }

        $orderQuery = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled');

        $paymentQuery = Payment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('order', function ($q) {
                $q->where('status', '!=', 'cancelled');
            });

        if ($type !== 'all') {
            $orderQuery->where('type', $type);
            $paymentQuery->whereHas('order', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        $orderStats = $orderQuery
            ->select(
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN type = "supplier_purchase" THEN 1 ELSE 0 END) as purchase_orders'),
                DB::raw('SUM(CASE WHEN type = "distributor_order" THEN 1 ELSE 0 END) as distributor_orders'),
                DB::raw('SUM(CASE WHEN type = "agent_order" THEN 1 ELSE 0 END) as agent_orders'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_orders'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('SUM(CASE WHEN type = "supplier_purchase" THEN total ELSE 0 END) as purchase_amount'),
                DB::raw('SUM(CASE WHEN type != "supplier_purchase" THEN total ELSE 0 END) as sales_amount'),
            )
            ->first();

        $paymentStats = $paymentQuery
            ->select(
                DB::raw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income'),
                DB::raw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "cash" THEN amount ELSE 0 END) as cash_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "bank_transfer" THEN amount ELSE 0 END) as bank_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "alipay" THEN amount ELSE 0 END) as alipay_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "wechat" THEN amount ELSE 0 END) as wechat_income'),
            )
            ->first();

        $income = $paymentStats?->income ?? 0;
        $expense = $paymentStats?->expense ?? 0;
        $totalAmount = $orderStats?->total_amount ?? 0;

        if ($type === 'supplier_purchase') {
            $paidAmount = $expense;
            $unpaidAmount = $totalAmount - $expense;
        } else {
            $paidAmount = $income;
            $unpaidAmount = $totalAmount - $income;
        }

        $report->fill([
            'total_orders' => (int) ($orderStats?->total_orders ?? 0),
            'purchase_orders' => (int) ($orderStats?->purchase_orders ?? 0),
            'distributor_orders' => (int) ($orderStats?->distributor_orders ?? 0),
            'agent_orders' => (int) ($orderStats?->agent_orders ?? 0),
            'completed_orders' => (int) ($orderStats?->completed_orders ?? 0),
            'pending_orders' => (int) ($orderStats?->pending_orders ?? 0),
            'total_amount' => (float) $totalAmount,
            'purchase_amount' => (float) ($orderStats?->purchase_amount ?? 0),
            'sales_amount' => (float) ($orderStats?->sales_amount ?? 0),
            'paid_amount' => (float) $paidAmount,
            'unpaid_amount' => (float) $unpaidAmount,
            'total_income' => (float) $income,
            'total_expense' => (float) $expense,
            'net_profit' => (float) ($income - $expense),
            'cash_income' => (float) ($paymentStats?->cash_income ?? 0),
            'bank_transfer_income' => (float) ($paymentStats?->bank_income ?? 0),
            'alipay_income' => (float) ($paymentStats?->alipay_income ?? 0),
            'wechat_income' => (float) ($paymentStats?->wechat_income ?? 0),
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);

        $report->save();

        return $report;
    }

    public static function generateRange($dateFrom, $dateTo, $type = 'all', $userId = null): array
    {
        $start = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);
        $reports = [];

        $current = $start->copy();
        while ($current->lte($end)) {
            $reports[] = self::generateForDate($current, $type, $userId);
            $current->addDay();
        }

        return $reports;
    }

    public function toArrayResponse(): array
    {
        return [
            'id' => $this->id,
            'report_date' => $this->report_date->format('Y-m-d'),
            'type' => $this->type,
            'orders' => [
                'total' => $this->total_orders,
                'purchase' => $this->purchase_orders,
                'distributor' => $this->distributor_orders,
                'agent' => $this->agent_orders,
                'completed' => $this->completed_orders,
                'pending' => $this->pending_orders,
            ],
            'amounts' => [
                'total' => $this->total_amount,
                'purchase' => $this->purchase_amount,
                'sales' => $this->sales_amount,
                'paid' => $this->paid_amount,
                'unpaid' => $this->unpaid_amount,
            ],
            'payments' => [
                'income' => $this->total_income,
                'expense' => $this->total_expense,
                'net' => $this->net_profit,
                'by_method' => [
                    'cash' => $this->cash_income,
                    'bank_transfer' => $this->bank_transfer_income,
                    'alipay' => $this->alipay_income,
                    'wechat' => $this->wechat_income,
                ],
            ],
            'remark' => $this->remark,
            'generated_at' => $this->generated_at?->format('Y-m-d H:i:s'),
            'generated_by' => $this->generatedBy?->name,
        ];
    }
}
