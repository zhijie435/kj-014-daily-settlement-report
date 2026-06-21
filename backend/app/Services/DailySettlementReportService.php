<?php

namespace App\Services;

use App\Exceptions\ReportGenerateException;
use App\Exceptions\ReportStatusException;
use App\Models\DailySettlementReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DailySettlementReportService
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getReports(array $filters): LengthAwarePaginator
    {
        $query = DailySettlementReport::query()
            ->with(['generatedBy', 'confirmedBy', 'auditedBy'])
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->byType($filters['type'] ?? null)
            ->orderByDate();

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 30);

        return $query->paginate(min($perPage, 100));
    }

    public function getReport(DailySettlementReport $report): DailySettlementReport
    {
        return $report->load(['generatedBy', 'confirmedBy', 'auditedBy']);
    }

    public function generateReport(string $reportDate, string $type, ?string $remark = null): DailySettlementReport
    {
        $this->validateGenerateDate($reportDate);

        return DB::transaction(function () use ($reportDate, $type, $remark) {
            $report = DailySettlementReport::generateForDate(
                $reportDate,
                $type,
                $this->user->id
            );

            if ($remark !== null) {
                $report->update(['remark' => $remark]);
            }

            return $report;
        });
    }

    public function generateBatch(string $dateFrom, string $dateTo, string $type): array
    {
        $this->validateDateRange($dateFrom, $dateTo);

        $start = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);
        $reports = [];

        $current = $start->copy();
        while ($current->lte($end)) {
            $reports[] = $this->generateReport($current->format('Y-m-d'), $type);
            $current->addDay();
        }

        return $reports;
    }

    public function regenerateReport(DailySettlementReport $report): DailySettlementReport
    {
        if (!$report->canRegenerate()) {
            throw ReportStatusException::notRegeneratable($report->status);
        }

        return DB::transaction(function () use ($report) {
            return DailySettlementReport::generateForDate(
                $report->report_date,
                $report->type,
                $this->user->id
            );
        });
    }

    public function updateReport(DailySettlementReport $report, array $data): DailySettlementReport
    {
        if (!$report->canEdit()) {
            throw ReportStatusException::notEditable($report->status);
        }

        $report->update($data);

        return $report;
    }

    public function deleteReport(DailySettlementReport $report): void
    {
        if (!$report->canDelete()) {
            throw ReportStatusException::notDeletable($report->status);
        }

        $report->delete();
    }

    public function confirmReport(DailySettlementReport $report): DailySettlementReport
    {
        return DB::transaction(function () use ($report) {
            return $report->confirm($this->user->id);
        });
    }

    public function auditReport(DailySettlementReport $report): DailySettlementReport
    {
        return DB::transaction(function () use ($report) {
            return $report->audit($this->user->id);
        });
    }

    public function lockReport(DailySettlementReport $report): DailySettlementReport
    {
        return DB::transaction(function () use ($report) {
            return $report->lock();
        });
    }

    public function revertToDraft(DailySettlementReport $report): DailySettlementReport
    {
        return DB::transaction(function () use ($report) {
            return $report->revertToDraft($this->user->id);
        });
    }

    public function getSummary(array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $filters['date_to'] ?? now()->format('Y-m-d');
        $type = $filters['type'] ?? DailySettlementReport::TYPE_ALL;

        $query = DailySettlementReport::query()
            ->dateRange($dateFrom, $dateTo)
            ->byType($type);

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

        return [
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
    }

    public function exportReports(array $filters): Collection
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $filters['date_to'] ?? now()->format('Y-m-d');
        $type = $filters['type'] ?? DailySettlementReport::TYPE_ALL;

        return DailySettlementReport::query()
            ->dateRange($dateFrom, $dateTo)
            ->byType($type)
            ->orderByDate()
            ->get();
    }

    public function calculateExportSummary(Collection $reports): array
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

    protected function validateGenerateDate(string $date): void
    {
        $reportDate = \Carbon\Carbon::parse($date);

        if ($reportDate->isFuture()) {
            throw ReportGenerateException::futureDate($reportDate->format('Y-m-d'));
        }
    }

    protected function validateDateRange(string $dateFrom, string $dateTo): void
    {
        $start = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);
        $days = $start->diffInDays($end) + 1;

        $maxDays = DailySettlementReport::MAX_GENERATE_DAYS;
        if ($days > $maxDays) {
            throw ReportGenerateException::dateRangeTooLarge($days, $maxDays);
        }

        if ($end->isFuture()) {
            throw ReportGenerateException::futureDate($end->format('Y-m-d'));
        }
    }
}
