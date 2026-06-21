<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySettlementReport;
use App\Services\DailySettlementReportService;
use Illuminate\Http\Request;

class DailySettlementReportController extends Controller
{
    protected DailySettlementReportService $service;

    public function __construct(DailySettlementReportService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->authorize('report.view');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
            'status' => 'nullable|in:draft,confirmed,audited,locked',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $reports = $this->service->getReports($validated);

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

        $report = $this->service->getReport($dailySettlementReport);

        return response()->json($report->toArrayResponse());
    }

    public function store(Request $request)
    {
        $this->authorize('report.generate');

        $validated = $request->validate([
            'report_date' => 'required|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
            'remark' => 'nullable|string|max:1000',
        ]);

        $type = $validated['type'] ?? DailySettlementReport::TYPE_ALL;
        $remark = $validated['remark'] ?? null;

        $report = $this->service->generateReport(
            $validated['report_date'],
            $type,
            $remark
        );

        return response()->json([
            'message' => '日结算报表生成成功',
            'report' => $report->toArrayResponse(),
        ], 201);
    }

    public function generateBatch(Request $request)
    {
        $this->authorize('report.generate');

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
        ]);

        $type = $validated['type'] ?? DailySettlementReport::TYPE_ALL;

        $reports = $this->service->generateBatch(
            $validated['date_from'],
            $validated['date_to'],
            $type
        );

        return response()->json([
            'message' => '批量生成日结算报表成功',
            'generated_count' => count($reports),
            'reports' => array_map(fn($r) => $r->toArrayResponse(), $reports),
        ], 201);
    }

    public function regenerate(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.regenerate');

        $report = $this->service->regenerateReport($dailySettlementReport);

        return response()->json([
            'message' => '日结算报表重新生成成功',
            'report' => $report->toArrayResponse(),
        ]);
    }

    public function update(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.manage');

        $validated = $request->validate([
            'remark' => 'nullable|string|max:1000',
        ]);

        $report = $this->service->updateReport($dailySettlementReport, $validated);

        return response()->json([
            'message' => '日结算报表更新成功',
            'report' => $report->toArrayResponse(),
        ]);
    }

    public function destroy(DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.delete');

        $this->service->deleteReport($dailySettlementReport);

        return response()->json(['message' => '日结算报表删除成功']);
    }

    public function confirm(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.confirm');

        $report = $this->service->confirmReport($dailySettlementReport);

        return response()->json([
            'message' => '日结算报表确认成功',
            'report' => $report->toArrayResponse(),
        ]);
    }

    public function audit(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.audit');

        $report = $this->service->auditReport($dailySettlementReport);

        return response()->json([
            'message' => '日结算报表审核成功',
            'report' => $report->toArrayResponse(),
        ]);
    }

    public function lock(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.lock');

        $report = $this->service->lockReport($dailySettlementReport);

        return response()->json([
            'message' => '日结算报表锁定成功',
            'report' => $report->toArrayResponse(),
        ]);
    }

    public function revertToDraft(Request $request, DailySettlementReport $dailySettlementReport)
    {
        $this->authorize('report.manage');

        $report = $this->service->revertToDraft($dailySettlementReport);

        return response()->json([
            'message' => '日结算报表已退回草稿',
            'report' => $report->toArrayResponse(),
        ]);
    }

    public function summary(Request $request)
    {
        $this->authorize('report.view');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
        ]);

        $summary = $this->service->getSummary($validated);

        return response()->json($summary);
    }

    public function export(Request $request)
    {
        $this->authorize('report.export');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:supplier_purchase,distributor_order,agent_order,all',
            'format' => 'nullable|in:csv',
        ]);

        $reports = $this->service->exportReports($validated);
        $summary = $this->service->calculateExportSummary($reports);

        $dateFrom = $validated['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $validated['date_to'] ?? now()->format('Y-m-d');
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
                '日期', '状态', '订单数', '已完成', '待处理',
                '采购订单', '分销订单', '代理订单',
                '总金额', '采购金额', '销售金额', '已收金额', '未收金额',
                '收入', '支出', '净额',
                '现金收入', '银行收入', '支付宝', '微信', '备注'
            ]);

            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->report_date->format('Y-m-d'),
                    $report->getStatusLabel(),
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
}
