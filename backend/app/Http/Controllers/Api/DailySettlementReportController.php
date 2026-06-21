<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySettlementReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailySettlementReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('report.view');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DailySettlementReport::query();

        if (isset($validated['date_from'])) {
            $query->where('report_date', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->where('report_date', '<=', $validated['date_to']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $reports = $query->with('generatedBy')
            ->orderBy('report_date', 'desc')
            ->paginate($validated['per_page'] ?? 30);

        $transformed = $reports->getCollection()->transform(function ($report) {
            return $report->toArrayResponse();
        });

        return response()->json([
            'data' => $transformed,
            'current_page' => $reports->currentPage(),
            'per_page' => $reports->perPage(),
            'total' => $reports->total(),
            'last_page' => $reports->lastPage(),
        ]);
    }

    public function show(DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.view');

        return response()->json($dailySettlementReport->load('generatedBy')->toArrayResponse());
    }

    public function store(Request $request)
    {
        $this->authorize('report.manage');

        $validated = $request->validate([
            'report_date' => 'required|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
            'remark' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $type = $validated['type'] ?? 'all';

        $report = DailySettlementReport::generateForDate(
            $validated['report_date'],
            $type,
            $userId
        );

        if (isset($validated['remark'])) {
            $report->update(['remark' => $validated['remark']]);
        }

        return response()->json([
            'message' => '日结算报表生成成功',
            'report' => $report->toArrayResponse(),
        ], 201);
    }

    public function generateBatch(Request $request)
    {
        $this->authorize('report.manage');

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
        ]);

        $userId = $request->user()->id;
        $type = $validated['type'] ?? 'all';

        $reports = DailySettlementReport::generateRange(
            $validated['date_from'],
            $validated['date_to'],
            $type,
            $userId
        );

        return response()->json([
            'message' => '批量生成日结算报表成功',
            'generated_count' => count($reports),
            'reports' => array_map(fn($r) => $r->toArrayResponse(), $reports),
        ], 201);
    }

    public function regenerate(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.manage');

        $userId = $request->user()->id;

        $report = DailySettlementReport::generateForDate(
            $dailySettlementReport->report_date,
            $dailySettlementReport->type,
            $userId
        );

        return response()->json([
            'message' => '日结算报表重新生成成功',
            'report' => $report->toArrayResponse(),
        ]);
    }

    public function update(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.manage');

        $validated = $request->validate([
            'remark' => 'nullable|string',
        ]);

        $dailySettlementReport->update($validated);

        return response()->json([
            'message' => '日结算报表更新成功',
            'report' => $dailySettlementReport->toArrayResponse(),
        ]);
    }

    public function destroy(DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.manage');

        $dailySettlementReport->delete();

        return response()->json(['message' => '日结算报表删除成功']);
    }

    public function summary(Request $request)
    {
        $this->authorize('report.view');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
        ]);

        $dateFrom = $validated['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $validated['date_to'] ?? now()->format('Y-m-d');
        $type = $validated['type'] ?? 'all';

        $query = DailySettlementReport::query()
            ->whereBetween('report_date', [$dateFrom, $dateTo])
            ->where('type', $type);

        $summary = $query->select(
            DB::raw('COUNT(*) as total_days'),
            DB::raw('SUM(total_orders) as total_orders'),
            DB::raw('SUM(completed_orders) as total_completed_orders'),
            DB::raw('SUM(pending_orders) as total_pending_orders'),
            DB::raw('SUM(purchase_orders) as total_purchase_orders'),
            DB::raw('SUM(distributor_orders) as total_distributor_orders'),
            DB::raw('SUM(agent_orders) as total_agent_orders'),
            DB::raw('SUM(total_amount) as total_amount'),
            DB::raw('SUM(purchase_amount) as total_purchase_amount'),
            DB::raw('SUM(sales_amount) as total_sales_amount'),
            DB::raw('SUM(paid_amount) as total_paid_amount'),
            DB::raw('SUM(unpaid_amount) as total_unpaid_amount'),
            DB::raw('SUM(total_income) as total_income'),
            DB::raw('SUM(total_expense) as total_expense'),
            DB::raw('SUM(net_profit) as net_profit'),
            DB::raw('SUM(cash_income) as cash_income'),
            DB::raw('SUM(bank_transfer_income) as bank_transfer_income'),
            DB::raw('SUM(alipay_income) as alipay_income'),
            DB::raw('SUM(wechat_income) as wechat_income'),
        )->first();

        $days = (int) ($summary?->total_days ?? 0);
        $totalOrders = (int) ($summary?->total_orders ?? 0);
        $totalAmount = (float) ($summary?->total_amount ?? 0);

        $result = [
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'total_days' => $days,
            'total_orders' => $totalOrders,
            'total_completed_orders' => (int) ($summary?->total_completed_orders ?? 0),
            'total_pending_orders' => (int) ($summary?->total_pending_orders ?? 0),
            'total_purchase_orders' => (int) ($summary?->total_purchase_orders ?? 0),
            'total_distributor_orders' => (int) ($summary?->total_distributor_orders ?? 0),
            'total_agent_orders' => (int) ($summary?->total_agent_orders ?? 0),
            'total_amount' => round($totalAmount, 2),
            'total_purchase_amount' => round((float) ($summary?->total_purchase_amount ?? 0), 2),
            'total_sales_amount' => round((float) ($summary?->total_sales_amount ?? 0), 2),
            'total_paid_amount' => round((float) ($summary?->total_paid_amount ?? 0), 2),
            'total_unpaid_amount' => round((float) ($summary?->total_unpaid_amount ?? 0), 2),
            'total_income' => round((float) ($summary?->total_income ?? 0), 2),
            'total_expense' => round((float) ($summary?->total_expense ?? 0), 2),
            'net_profit' => round((float) ($summary?->net_profit ?? 0), 2),
            'avg_daily_orders' => $days > 0 ? round($totalOrders / $days, 2) : 0,
            'avg_daily_amount' => $days > 0 ? round($totalAmount / $days, 2) : 0,
            'payment_methods' => [
                'cash' => round((float) ($summary?->cash_income ?? 0), 2),
                'bank_transfer' => round((float) ($summary?->bank_transfer_income ?? 0), 2),
                'alipay' => round((float) ($summary?->alipay_income ?? 0), 2),
                'wechat' => round((float) ($summary?->wechat_income ?? 0), 2),
            ],
        ];

        return response()->json($result);
    }

    public function export(Request $request)
    {
        $this->authorize('report.view');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
            'format' => 'nullable|in:csv',
        ]);

        $dateFrom = $validated['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $validated['date_to'] ?? now()->format('Y-m-d');
        $type = $validated['type'] ?? 'all';

        $reports = DailySettlementReport::query()
            ->whereBetween('report_date', [$dateFrom, $dateTo])
            ->where('type', $type)
            ->orderBy('report_date', 'desc')
            ->get();

        $summary = $this->calculateExportSummary($reports);

        $filename = "daily_settlement_reports_{$dateFrom}_to_{$dateTo}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($reports, $summary) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['=== 日结算报表汇总 ===']);
            fputcsv($handle, []);
            fputcsv($handle, ['指标', '数值']);
            fputcsv($handle, ['统计天数', $summary['total_days']]);
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
                '日期', '订单数', '已完成', '待处理',
                '采购订单', '分销订单', '代理订单',
                '总金额', '采购金额', '销售金额', '已收金额', '未收金额',
                '收入', '支出', '净额',
                '现金收入', '银行收入', '支付宝', '微信', '备注'
            ]);

            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->report_date->format('Y-m-d'),
                    $report->total_orders,
                    $report->completed_orders,
                    $report->pending_orders,
                    $report->purchase_orders,
                    $report->distributor_orders,
                    $report->agent_orders,
                    $report->total_amount,
                    $report->purchase_amount,
                    $report->sales_amount,
                    $report->paid_amount,
                    $report->unpaid_amount,
                    $report->total_income,
                    $report->total_expense,
                    $report->net_profit,
                    $report->cash_income,
                    $report->bank_transfer_income,
                    $report->alipay_income,
                    $report->wechat_income,
                    $report->remark,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function calculateExportSummary($reports)
    {
        $summary = [
            'total_days' => $reports->count(),
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

        foreach ($reports as $report) {
            $summary['total_orders'] += $report->total_orders;
            $summary['total_completed_orders'] += $report->completed_orders;
            $summary['total_pending_orders'] += $report->pending_orders;
            $summary['total_purchase_orders'] += $report->purchase_orders;
            $summary['total_distributor_orders'] += $report->distributor_orders;
            $summary['total_agent_orders'] += $report->agent_orders;

            $summary['total_amount'] += $report->total_amount;
            $summary['total_purchase_amount'] += $report->purchase_amount;
            $summary['total_sales_amount'] += $report->sales_amount;
            $summary['total_paid_amount'] += $report->paid_amount;
            $summary['total_unpaid_amount'] += $report->unpaid_amount;

            $summary['total_income'] += $report->total_income;
            $summary['total_expense'] += $report->total_expense;
            $summary['net_profit'] += $report->net_profit;

            $summary['payment_methods']['cash'] += $report->cash_income;
            $summary['payment_methods']['bank_transfer'] += $report->bank_transfer_income;
            $summary['payment_methods']['alipay'] += $report->alipay_income;
            $summary['payment_methods']['wechat'] += $report->wechat_income;
        }

        $days = $summary['total_days'];
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
}
