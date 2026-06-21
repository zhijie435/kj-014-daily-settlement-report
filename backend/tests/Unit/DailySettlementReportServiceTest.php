<?php

namespace Tests\Unit;

use App\Services\DailySettlementReportService;
use App\Models\DailySettlementReport;
use App\Models\User;
use App\Exceptions\ReportGenerateException;
use App\Exceptions\ReportStatusException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailySettlementReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DailySettlementReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'platform']);
        $this->service = new DailySettlementReportService($this->user);
    }

    public function test_get_reports_with_filters(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            DailySettlementReport::factory()->create([
                'report_date' => "2024-01-{$i}",
                'type' => DailySettlementReport::TYPE_ALL,
                'status' => DailySettlementReport::STATUS_DRAFT,
            ]);
        }

        $filters = [
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'type' => DailySettlementReport::TYPE_ALL,
            'status' => DailySettlementReport::STATUS_DRAFT,
            'per_page' => 10,
        ];

        $result = $this->service->getReports($filters);

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(5, $result->total());
        $this->assertEquals(10, $result->perPage());
    }

    public function test_get_reports_pagination_limit(): void
    {
        DailySettlementReport::factory()->count(10)->create();

        $filters = ['per_page' => 200];
        $result = $this->service->getReports($filters);

        $this->assertEquals(100, $result->perPage());
    }

    public function test_get_report_loads_relations(): void
    {
        $report = DailySettlementReport::factory()->create([
            'generated_by' => $this->user->id,
        ]);

        $result = $this->service->getReport($report);

        $this->assertTrue($result->relationLoaded('generatedBy'));
        $this->assertTrue($result->relationLoaded('confirmedBy'));
        $this->assertTrue($result->relationLoaded('auditedBy'));
    }

    public function test_generate_report_creates_draft_report(): void
    {
        $reportDate = now()->subDay()->format('Y-m-d');

        $result = $this->service->generateReport($reportDate, DailySettlementReport::TYPE_ALL);

        $this->assertInstanceOf(DailySettlementReport::class, $result);
        $this->assertEquals($reportDate, $result->report_date->format('Y-m-d'));
        $this->assertEquals(DailySettlementReport::STATUS_DRAFT, $result->status);
        $this->assertEquals($this->user->id, $result->generated_by);
    }

    public function test_generate_report_with_remark(): void
    {
        $reportDate = now()->subDay()->format('Y-m-d');
        $remark = 'Test remark';

        $result = $this->service->generateReport($reportDate, DailySettlementReport::TYPE_ALL, $remark);

        $this->assertEquals($remark, $result->remark);
    }

    public function test_generate_report_throws_for_future_date(): void
    {
        $this->expectException(ReportGenerateException::class);

        $futureDate = now()->addDay()->format('Y-m-d');
        $this->service->generateReport($futureDate, DailySettlementReport::TYPE_ALL);
    }

    public function test_generate_batch_creates_multiple_reports(): void
    {
        $dateFrom = now()->subDays(5)->format('Y-m-d');
        $dateTo = now()->subDay()->format('Y-m-d');

        $reports = $this->service->generateBatch($dateFrom, $dateTo, DailySettlementReport::TYPE_ALL);

        $this->assertCount(5, $reports);
        $this->assertCount(5, DailySettlementReport::all());
    }

    public function test_generate_batch_throws_for_large_range(): void
    {
        $this->expectException(ReportGenerateException::class);

        $dateFrom = now()->subDays(100)->format('Y-m-d');
        $dateTo = now()->subDay()->format('Y-m-d');
        $this->service->generateBatch($dateFrom, $dateTo, DailySettlementReport::TYPE_ALL);
    }

    public function test_generate_batch_throws_for_future_end_date(): void
    {
        $this->expectException(ReportGenerateException::class);

        $dateFrom = now()->format('Y-m-d');
        $dateTo = now()->addDay()->format('Y-m-d');
        $this->service->generateBatch($dateFrom, $dateTo, DailySettlementReport::TYPE_ALL);
    }

    public function test_regenerate_report_from_draft(): void
    {
        $report = DailySettlementReport::factory()->create([
            'report_date' => now()->subDay()->format('Y-m-d'),
            'status' => DailySettlementReport::STATUS_DRAFT,
            'total_orders' => 10,
        ]);

        $result = $this->service->regenerateReport($report);

        $this->assertInstanceOf(DailySettlementReport::class, $result);
        $this->assertEquals(DailySettlementReport::STATUS_DRAFT, $result->status);
    }

    public function test_regenerate_report_from_confirmed(): void
    {
        $report = DailySettlementReport::factory()->create([
            'report_date' => now()->subDay()->format('Y-m-d'),
            'status' => DailySettlementReport::STATUS_CONFIRMED,
        ]);

        $result = $this->service->regenerateReport($report);

        $this->assertInstanceOf(DailySettlementReport::class, $result);
    }

    public function test_regenerate_report_throws_from_audited(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_AUDITED,
        ]);

        $this->service->regenerateReport($report);
    }

    public function test_regenerate_report_throws_from_locked(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_LOCKED,
        ]);

        $this->service->regenerateReport($report);
    }

    public function test_update_report_in_draft_status(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
            'remark' => 'old remark',
        ]);

        $result = $this->service->updateReport($report, ['remark' => 'new remark']);

        $this->assertEquals('new remark', $result->remark);
    }

    public function test_update_report_in_confirmed_status(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
            'remark' => 'old remark',
        ]);

        $result = $this->service->updateReport($report, ['remark' => 'new remark']);

        $this->assertEquals('new remark', $result->remark);
    }

    public function test_update_report_throws_from_audited(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_AUDITED,
        ]);

        $this->service->updateReport($report, ['remark' => 'test']);
    }

    public function test_delete_report_in_draft_status(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]);

        $this->service->deleteReport($report);

        $this->assertSoftDeleted($report);
    }

    public function test_delete_report_throws_from_confirmed(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
        ]);

        $this->service->deleteReport($report);
    }

    public function test_confirm_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]);

        $result = $this->service->confirmReport($report);

        $this->assertTrue($result->isConfirmed());
        $this->assertEquals($this->user->id, $result->confirmed_by);
        $this->assertNotNull($result->confirmed_at);
    }

    public function test_audit_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
        ]);

        $result = $this->service->auditReport($report);

        $this->assertTrue($result->isAudited());
        $this->assertEquals($this->user->id, $result->audited_by);
        $this->assertNotNull($result->audited_at);
    }

    public function test_lock_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_AUDITED,
        ]);

        $result = $this->service->lockReport($report);

        $this->assertTrue($result->isLocked());
    }

    public function test_revert_to_draft(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
        ]);

        $result = $this->service->revertToDraft($report);

        $this->assertTrue($result->isDraft());
        $this->assertNull($result->confirmed_by);
        $this->assertNull($result->confirmed_at);
    }

    public function test_get_summary(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            DailySettlementReport::factory()->create([
                'report_date' => "2024-01-{$i}",
                'type' => DailySettlementReport::TYPE_ALL,
                'total_orders' => 10,
                'total_amount' => 100.00,
                'total_income' => 80.00,
                'total_expense' => 50.00,
                'net_profit' => 30.00,
            ]);
        }

        $filters = [
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'type' => DailySettlementReport::TYPE_ALL,
        ];

        $summary = $this->service->getSummary($filters);

        $this->assertArrayHasKey('total_days', $summary);
        $this->assertArrayHasKey('total_orders', $summary);
        $this->assertArrayHasKey('total_amount', $summary);
        $this->assertArrayHasKey('net_profit', $summary);
        $this->assertArrayHasKey('avg_daily_orders', $summary);
        $this->assertArrayHasKey('avg_daily_amount', $summary);
        $this->assertArrayHasKey('payment_methods', $summary);
        $this->assertIsArray($summary['payment_methods']);
    }

    public function test_get_summary_with_default_filters(): void
    {
        $summary = $this->service->getSummary([]);

        $this->assertArrayHasKey('date_range', $summary);
        $this->assertNotNull($summary['date_range']['from']);
        $this->assertNotNull($summary['date_range']['to']);
    }

    public function test_get_summary_with_zero_days(): void
    {
        $filters = [
            'date_from' => '2099-01-01',
            'date_to' => '2099-01-31',
        ];

        $summary = $this->service->getSummary($filters);

        $this->assertEquals(0, $summary['total_days']);
        $this->assertEquals(0, $summary['avg_daily_orders']);
        $this->assertEquals(0, $summary['avg_daily_amount']);
    }

    public function test_export_reports(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            DailySettlementReport::factory()->create([
                'report_date' => "2024-01-{$i}",
            ]);
        }

        $filters = [
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'type' => DailySettlementReport::TYPE_ALL,
        ];

        $reports = $this->service->exportReports($filters);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $reports);
        $this->assertCount(5, $reports);
    }

    public function test_calculate_export_summary(): void
    {
        $reports = new \Illuminate\Database\Eloquent\Collection([
            new DailySettlementReport([
                'total_orders' => 10,
                'completed_orders' => 8,
                'pending_orders' => 2,
                'purchase_orders' => 3,
                'distributor_orders' => 4,
                'agent_orders' => 3,
                'total_amount' => 1000.00,
                'purchase_amount' => 400.00,
                'sales_amount' => 600.00,
                'paid_amount' => 800.00,
                'unpaid_amount' => 200.00,
                'total_income' => 800.00,
                'total_expense' => 400.00,
                'net_profit' => 400.00,
                'cash_income' => 200.00,
                'bank_transfer_income' => 300.00,
                'alipay_income' => 150.00,
                'wechat_income' => 150.00,
            ]),
            new DailySettlementReport([
                'total_orders' => 20,
                'completed_orders' => 15,
                'pending_orders' => 5,
                'purchase_orders' => 6,
                'distributor_orders' => 8,
                'agent_orders' => 6,
                'total_amount' => 2000.00,
                'purchase_amount' => 800.00,
                'sales_amount' => 1200.00,
                'paid_amount' => 1600.00,
                'unpaid_amount' => 400.00,
                'total_income' => 1600.00,
                'total_expense' => 800.00,
                'net_profit' => 800.00,
                'cash_income' => 400.00,
                'bank_transfer_income' => 600.00,
                'alipay_income' => 300.00,
                'wechat_income' => 300.00,
            ]),
        ]);

        $summary = $this->service->calculateExportSummary($reports);

        $this->assertEquals(2, $summary['total_days']);
        $this->assertEquals(30, $summary['total_orders']);
        $this->assertEquals(3000.00, $summary['total_amount']);
        $this->assertEquals(15, $summary['avg_daily_orders']);
        $this->assertEquals(1500.00, $summary['avg_daily_amount']);
        $this->assertEquals(600.00, $summary['payment_methods']['cash']);
        $this->assertEquals(900.00, $summary['payment_methods']['bank_transfer']);
        $this->assertEquals(450.00, $summary['payment_methods']['alipay']);
        $this->assertEquals(450.00, $summary['payment_methods']['wechat']);
    }

    public function test_calculate_export_summary_with_empty_collection(): void
    {
        $reports = new \Illuminate\Database\Eloquent\Collection([]);

        $summary = $this->service->calculateExportSummary($reports);

        $this->assertEquals(0, $summary['total_days']);
        $this->assertEquals(0, $summary['total_orders']);
        $this->assertEquals(0, $summary['avg_daily_orders']);
        $this->assertEquals(0, $summary['avg_daily_amount']);
    }
}
