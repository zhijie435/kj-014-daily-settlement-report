<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dailySettlement(Request $request)
    {
        $this->authorize('report.view');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
        ]);

        $dateFrom = $validated['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $validated['date_to'] ?? now()->format('Y-m-d');

        $startDate = \Carbon\Carbon::parse($dateFrom)->startOfDay();
        $endDate = \Carbon\Carbon::parse($dateTo)->endOfDay();

        $type = $validated['type'] ?? null;
        if ($type === 'all') {
            $type = null;
        }

        $dailyData = $this->generateDailyData($startDate, $endDate, $type);

        $summary = $this->calculateSummary($dailyData);

        return response()->json([
            'summary' => $summary,
            'daily_data' => $dailyData,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ]);
    }

    protected function generateDailyData($startDate, $endDate, $type = null)
    {
        $orderQuery = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNot('status', 'cancelled');

        $paymentQuery = Payment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('order', function ($q) {
                $q->whereNot('status', 'cancelled');
            });

        if ($type) {
            $orderQuery->where('type', $type);
            $paymentQuery->whereHas('order', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        $ordersByDate = $orderQuery
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN type = "supplier_purchase" THEN 1 ELSE 0 END) as purchase_orders'),
                DB::raw('SUM(CASE WHEN type = "distributor_order" THEN 1 ELSE 0 END) as distributor_orders'),
                DB::raw('SUM(CASE WHEN type = "agent_order" THEN 1 ELSE 0 END) as agent_orders'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_orders'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('SUM(CASE WHEN type = "supplier_purchase" THEN total ELSE 0 END) as purchase_amount'),
                DB::raw('SUM(CASE WHEN type != "supplier_purchase" THEN total ELSE 0 END) as sales_amount'),
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('date');

        $completedOrdersQuery = Order::query()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->whereNot('status', 'cancelled');

        if ($type) {
            $completedOrdersQuery->where('type', $type);
        }

        $completedOrdersByDate = $completedOrdersQuery
            ->select(
                DB::raw('DATE(completed_at) as date'),
                DB::raw('COUNT(*) as completed_orders'),
            )
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->get()
            ->keyBy('date');

        $paymentsByDate = $paymentQuery
            ->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income'),
                DB::raw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "cash" THEN amount ELSE 0 END) as cash_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "bank_transfer" THEN amount ELSE 0 END) as bank_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "alipay" THEN amount ELSE 0 END) as alipay_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "wechat" THEN amount ELSE 0 END) as wechat_income'),
            )
            ->groupBy(DB::raw('DATE(payment_date)'))
            ->get()
            ->keyBy('date');

        $dailyData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $orders = $ordersByDate->get($dateStr);
            $completedOrders = $completedOrdersByDate->get($dateStr);
            $payments = $paymentsByDate->get($dateStr);

            $income = $payments?->income ?? 0;
            $expense = $payments?->expense ?? 0;

            $dailyData[] = [
                'date' => $dateStr,
                'day_of_week' => $currentDate->dayOfWeek,
                'day_name' => $currentDate->format('l'),
                'orders' => [
                    'total' => (int) ($orders?->total_orders ?? 0),
                    'purchase' => (int) ($orders?->purchase_orders ?? 0),
                    'distributor' => (int) ($orders?->distributor_orders ?? 0),
                    'agent' => (int) ($orders?->agent_orders ?? 0),
                    'completed' => (int) ($completedOrders?->completed_orders ?? 0),
                    'pending' => (int) ($orders?->pending_orders ?? 0),
                ],
                'amounts' => [
                    'total' => (float) ($orders?->total_amount ?? 0),
                    'purchase' => (float) ($orders?->purchase_amount ?? 0),
                    'sales' => (float) ($orders?->sales_amount ?? 0),
                    'paid' => (float) $income,
                    'unpaid' => (float) (($orders?->total_amount ?? 0) - $income),
                ],
                'payments' => [
                    'income' => (float) $income,
                    'expense' => (float) $expense,
                    'net' => (float) ($income - $expense),
                    'by_method' => [
                        'cash' => (float) ($payments?->cash_income ?? 0),
                        'bank_transfer' => (float) ($payments?->bank_income ?? 0),
                        'alipay' => (float) ($payments?->alipay_income ?? 0),
                        'wechat' => (float) ($payments?->wechat_income ?? 0),
                    ],
                ],
            ];

            $currentDate->addDay();
        }

        return array_reverse($dailyData);
    }

    protected function calculateSummary($dailyData)
    {
        $summary = [
            'total_orders' => 0,
            'total_completed_orders' => 0,
            'total_pending_orders' => 0,
            'total_purchase_orders' => 0,
            'total_distributor_orders' => 0,
            'total_agent_orders' => 0,
            'total_amount' => 0,
            'total_purchase_amount' => 0,
            'total_sales_amount' => 0,
            'total_paid_amount' => 0,
            'total_unpaid_amount' => 0,
            'total_income' => 0,
            'total_expense' => 0,
            'net_profit' => 0,
            'avg_daily_orders' => 0,
            'avg_daily_amount' => 0,
            'payment_methods' => [
                'cash' => 0,
                'bank_transfer' => 0,
                'alipay' => 0,
                'wechat' => 0,
            ],
        ];

        $days = count($dailyData);

        foreach ($dailyData as $day) {
            $summary['total_orders'] += $day['orders']['total'];
            $summary['total_completed_orders'] += $day['orders']['completed'];
            $summary['total_pending_orders'] += $day['orders']['pending'];
            $summary['total_purchase_orders'] += $day['orders']['purchase'];
            $summary['total_distributor_orders'] += $day['orders']['distributor'];
            $summary['total_agent_orders'] += $day['orders']['agent'];

            $summary['total_amount'] += $day['amounts']['total'];
            $summary['total_purchase_amount'] += $day['amounts']['purchase'];
            $summary['total_sales_amount'] += $day['amounts']['sales'];
            $summary['total_paid_amount'] += $day['amounts']['paid'];
            $summary['total_unpaid_amount'] += $day['amounts']['unpaid'];

            $summary['total_income'] += $day['payments']['income'];
            $summary['total_expense'] += $day['payments']['expense'];
            $summary['net_profit'] += $day['payments']['net'];

            $summary['payment_methods']['cash'] += $day['payments']['by_method']['cash'];
            $summary['payment_methods']['bank_transfer'] += $day['payments']['by_method']['bank_transfer'];
            $summary['payment_methods']['alipay'] += $day['payments']['by_method']['alipay'];
            $summary['payment_methods']['wechat'] += $day['payments']['by_method']['wechat'];
        }

        if ($days > 0) {
            $summary['avg_daily_orders'] = round($summary['total_orders'] / $days, 2);
            $summary['avg_daily_amount'] = round($summary['total_amount'] / $days, 2);
        }

        foreach ($summary as $key => $value) {
            if (is_float($value)) {
                $summary[$key] = round($value, 2);
            }
        }

        foreach ($summary['payment_methods'] as $method => $value) {
            $summary['payment_methods'][$method] = round($value, 2);
        }

        return $summary;
    }

    public function exportDailySettlement(Request $request)
    {
        $this->authorize('report.view');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
            'format' => 'nullable|in:csv,excel',
        ]);

        $dateFrom = $validated['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $validated['date_to'] ?? now()->format('Y-m-d');
        $format = $validated['format'] ?? 'csv';

        $startDate = \Carbon\Carbon::parse($dateFrom)->startOfDay();
        $endDate = \Carbon\Carbon::parse($dateTo)->endOfDay();

        $type = $validated['type'] ?? null;
        if ($type === 'all') {
            $type = null;
        }

        $dailyData = $this->generateDailyData($startDate, $endDate, $type);
        $summary = $this->calculateSummary($dailyData);

        if ($format === 'csv') {
            return $this->exportCsv($dailyData, $summary, $dateFrom, $dateTo);
        }

        return response()->json([
            'message' => 'Export format not supported yet',
        ], 400);
    }

    protected function exportCsv($dailyData, $summary, $dateFrom, $dateTo)
    {
        $filename = "daily_settlement_{$dateFrom}_to_{$dateTo}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($dailyData, $summary) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['=== 日结算报表汇总 ===']);
            fputcsv($handle, []);
            fputcsv($handle, ['指标', '数值']);
            fputcsv($handle, ['总订单数', $summary['total_orders']]);
            fputcsv($handle, ['已完成订单', $summary['total_completed_orders']]);
            fputcsv($handle, ['待处理订单', $summary['total_pending_orders']]);
            fputcsv($handle, ['采购订单数', $summary['total_purchase_orders']]);
            fputcsv($handle, ['分销商订单数', $summary['total_distributor_orders']]);
            fputcsv($handle, ['代理商订单数', $summary['total_agent_orders']]);
            fputcsv($handle, ['总金额', $summary['total_amount']]);
            fputcsv($handle, ['采购金额', $summary['total_purchase_amount']]);
            fputcsv($handle, ['销售金额', $summary['total_sales_amount']]);
            fputcsv($handle, ['已收金额', $summary['total_paid_amount']]);
            fputcsv($handle, ['未收金额', $summary['total_unpaid_amount']]);
            fputcsv($handle, ['总收入', $summary['total_income']]);
            fputcsv($handle, ['总支出', $summary['total_expense']]);
            fputcsv($handle, ['净利润', $summary['net_profit']]);
            fputcsv($handle, ['日均订单数', $summary['avg_daily_orders']]);
            fputcsv($handle, ['日均金额', $summary['avg_daily_amount']]);
            fputcsv($handle, []);

            fputcsv($handle, ['支付方式统计']);
            fputcsv($handle, ['现金', $summary['payment_methods']['cash']]);
            fputcsv($handle, ['银行转账', $summary['payment_methods']['bank_transfer']]);
            fputcsv($handle, ['支付宝', $summary['payment_methods']['alipay']]);
            fputcsv($handle, ['微信支付', $summary['payment_methods']['wechat']]);
            fputcsv($handle, []);

            fputcsv($handle, ['=== 每日明细 ===']);
            fputcsv($handle, []);
            fputcsv($handle, [
                '日期', '星期', '总订单数', '已完成', '待处理',
                '采购订单', '分销订单', '代理订单',
                '总金额', '采购金额', '销售金额', '已收金额', '未收金额',
                '收入', '支出', '净额',
                '现金收入', '银行收入', '支付宝', '微信'
            ]);

            foreach ($dailyData as $day) {
                fputcsv($handle, [
                    $day['date'],
                    $day['day_name'],
                    $day['orders']['total'],
                    $day['orders']['completed'],
                    $day['orders']['pending'],
                    $day['orders']['purchase'],
                    $day['orders']['distributor'],
                    $day['orders']['agent'],
                    $day['amounts']['total'],
                    $day['amounts']['purchase'],
                    $day['amounts']['sales'],
                    $day['amounts']['paid'],
                    $day['amounts']['unpaid'],
                    $day['payments']['income'],
                    $day['payments']['expense'],
                    $day['payments']['net'],
                    $day['payments']['by_method']['cash'],
                    $day['payments']['by_method']['bank_transfer'],
                    $day['payments']['by_method']['alipay'],
                    $day['payments']['by_method']['wechat'],
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
