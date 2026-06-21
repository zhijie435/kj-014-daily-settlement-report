<?php

namespace Tests\Unit;

use App\Models\DailySettlementReport;
use App\Exceptions\ReportStatusException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailySettlementReportModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'platform']);
    }

    public function test_status_checks(): void
    {
        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertTrue($report->isDraft());
        $this->assertFalse($report->isConfirmed());
        $this->assertFalse($report->isAudited());
        $this->assertFalse($report->isLocked());

        $report->status = DailySettlementReport::STATUS_CONFIRMED;
        $this->assertTrue($report->isConfirmed());

        $report->status = DailySettlementReport::STATUS_AUDITED;
        $this->assertTrue($report->isAudited());

        $report->status = DailySettlementReport::STATUS_LOCKED;
        $this->assertTrue($report->isLocked());
    }

    public function test_can_edit_permissions(): void
    {
        $draftReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertTrue($draftReport->canEdit());

        $confirmedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $this->assertTrue($confirmedReport->canEdit());

        $auditedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $this->assertFalse($auditedReport->canEdit());

        $lockedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_LOCKED]);
        $this->assertFalse($lockedReport->canEdit());
    }

    public function test_can_regenerate_permissions(): void
    {
        $draftReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertTrue($draftReport->canRegenerate());

        $confirmedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $this->assertTrue($confirmedReport->canRegenerate());

        $auditedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $this->assertFalse($auditedReport->canRegenerate());

        $lockedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_LOCKED]);
        $this->assertFalse($lockedReport->canRegenerate());
    }

    public function test_can_delete_permissions(): void
    {
        $draftReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertTrue($draftReport->canDelete());

        $confirmedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $this->assertFalse($confirmedReport->canDelete());

        $auditedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $this->assertFalse($auditedReport->canDelete());

        $lockedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_LOCKED]);
        $this->assertFalse($lockedReport->canDelete());
    }

    public function test_can_confirm_permissions(): void
    {
        $draftReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertTrue($draftReport->canConfirm());

        $confirmedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $this->assertFalse($confirmedReport->canConfirm());

        $auditedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $this->assertFalse($auditedReport->canConfirm());

        $lockedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_LOCKED]);
        $this->assertFalse($lockedReport->canConfirm());
    }

    public function test_can_audit_permissions(): void
    {
        $draftReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertFalse($draftReport->canAudit());

        $confirmedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $this->assertTrue($confirmedReport->canAudit());

        $auditedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $this->assertFalse($auditedReport->canAudit());

        $lockedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_LOCKED]);
        $this->assertFalse($lockedReport->canAudit());
    }

    public function test_can_lock_permissions(): void
    {
        $draftReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertFalse($draftReport->canLock());

        $confirmedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $this->assertFalse($confirmedReport->canLock());

        $auditedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $this->assertTrue($auditedReport->canLock());

        $lockedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_LOCKED]);
        $this->assertFalse($lockedReport->canLock());
    }

    public function test_can_revert_to_draft_permissions(): void
    {
        $draftReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertFalse($draftReport->canRevertToDraft());

        $confirmedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $this->assertTrue($confirmedReport->canRevertToDraft());

        $auditedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $this->assertFalse($auditedReport->canRevertToDraft());

        $lockedReport = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_LOCKED]);
        $this->assertFalse($lockedReport->canRevertToDraft());
    }

    public function test_confirm_transition_from_draft(): void
    {
        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);

        $result = $report->confirm($this->user->id);

        $this->assertTrue($result->isConfirmed());
        $this->assertEquals($this->user->id, $report->confirmed_by);
        $this->assertNotNull($report->confirmed_at);
    }

    public function test_confirm_fails_from_non_draft_status(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $report->confirm($this->user->id);
    }

    public function test_audit_transition_from_confirmed(): void
    {
        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);

        $result = $report->audit($this->user->id);

        $this->assertTrue($result->isAudited());
        $this->assertEquals($this->user->id, $report->audited_by);
        $this->assertNotNull($report->audited_at);
    }

    public function test_audit_fails_from_non_confirmed_status(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $report->audit($this->user->id);
    }

    public function test_lock_transition_from_audited(): void
    {
        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);

        $result = $report->lock();

        $this->assertTrue($result->isLocked());
    }

    public function test_lock_fails_from_non_audited_status(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);
        $report->lock();
    }

    public function test_revert_to_draft_from_confirmed(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
        ]);

        $result = $report->revertToDraft($this->user->id);

        $this->assertTrue($result->isDraft());
        $this->assertNull($report->confirmed_by);
        $this->assertNull($report->confirmed_at);
    }

    public function test_revert_to_draft_fails_from_non_confirmed_status(): void
    {
        $this->expectException(ReportStatusException::class);

        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_AUDITED]);
        $report->revertToDraft($this->user->id);
    }

    public function test_scope_by_status(): void
    {
        DailySettlementReport::factory()->count(3)->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        DailySettlementReport::factory()->count(2)->create(['status' => DailySettlementReport::STATUS_CONFIRMED]);

        $draftReports = DailySettlementReport::byStatus(DailySettlementReport::STATUS_DRAFT)->get();
        $this->assertCount(3, $draftReports);

        $confirmedReports = DailySettlementReport::byStatus(DailySettlementReport::STATUS_CONFIRMED)->get();
        $this->assertCount(2, $confirmedReports);
    }

    public function test_scope_date_range(): void
    {
        DailySettlementReport::factory()->create(['report_date' => '2024-01-15']);
        DailySettlementReport::factory()->create(['report_date' => '2024-01-20']);
        DailySettlementReport::factory()->create(['report_date' => '2024-01-25']);

        $reports = DailySettlementReport::dateRange('2024-01-18', '2024-01-22')->get();
        $this->assertCount(1, $reports);
        $this->assertEquals('2024-01-20', $reports->first()->report_date->format('Y-m-d'));
    }

    public function test_scope_by_type(): void
    {
        DailySettlementReport::factory()->create(['type' => DailySettlementReport::TYPE_SUPPLIER_PURCHASE]);
        DailySettlementReport::factory()->create(['type' => DailySettlementReport::TYPE_DISTRIBUTOR_ORDER]);
        DailySettlementReport::factory()->create(['type' => DailySettlementReport::TYPE_ALL]);

        $purchaseReports = DailySettlementReport::byType(DailySettlementReport::TYPE_SUPPLIER_PURCHASE)->get();
        $this->assertCount(1, $purchaseReports);

        $allReports = DailySettlementReport::byType(DailySettlementReport::TYPE_ALL)->get();
        $this->assertCount(3, $allReports);
    }

    public function test_scope_order_by_date(): void
    {
        DailySettlementReport::factory()->create(['report_date' => '2024-01-15']);
        DailySettlementReport::factory()->create(['report_date' => '2024-01-20']);
        DailySettlementReport::factory()->create(['report_date' => '2024-01-25']);

        $reportsDesc = DailySettlementReport::orderByDate('desc')->get();
        $this->assertEquals('2024-01-25', $reportsDesc->first()->report_date->format('Y-m-d'));

        $reportsAsc = DailySettlementReport::orderByDate('asc')->get();
        $this->assertEquals('2024-01-15', $reportsAsc->first()->report_date->format('Y-m-d'));
    }

    public function test_status_label_in_response(): void
    {
        $report = DailySettlementReport::factory()->create(['status' => DailySettlementReport::STATUS_DRAFT]);
        $this->assertEquals('草稿', $report->toArrayResponse()['status_label']);

        $report->status = DailySettlementReport::STATUS_CONFIRMED;
        $report->save();
        $this->assertEquals('已确认', $report->toArrayResponse()['status_label']);

        $report->status = DailySettlementReport::STATUS_AUDITED;
        $report->save();
        $this->assertEquals('已审核', $report->toArrayResponse()['status_label']);

        $report->status = DailySettlementReport::STATUS_LOCKED;
        $report->save();
        $this->assertEquals('已锁定', $report->toArrayResponse()['status_label']);
    }

    public function test_to_array_response_structure(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
            'generated_by' => $this->user->id,
        ]);

        $response = $report->toArrayResponse();

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('report_date', $response);
        $this->assertArrayHasKey('type', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('status_label', $response);
        $this->assertArrayHasKey('orders', $response);
        $this->assertArrayHasKey('amounts', $response);
        $this->assertArrayHasKey('payments', $response);
        $this->assertArrayHasKey('remark', $response);
        $this->assertArrayHasKey('can', $response);

        $this->assertArrayHasKey('edit', $response['can']);
        $this->assertArrayHasKey('regenerate', $response['can']);
        $this->assertArrayHasKey('delete', $response['can']);
        $this->assertArrayHasKey('confirm', $response['can']);
        $this->assertArrayHasKey('audit', $response['can']);
        $this->assertArrayHasKey('lock', $response['can']);
        $this->assertArrayHasKey('revert_to_draft', $response['can']);
    }

    public function test_set_report_date_attribute(): void
    {
        $report = new DailySettlementReport();
        $report->report_date = '2024-01-15';
        $this->assertEquals('2024-01-15', $report->report_date->format('Y-m-d'));

        $carbonDate = \Carbon\Carbon::parse('2024-02-20');
        $report->report_date = $carbonDate;
        $this->assertEquals('2024-02-20', $report->report_date->format('Y-m-d'));
    }

    public function test_generate_for_date_creates_new_report(): void
    {
        $report = DailySettlementReport::generateForDate('2024-01-15', DailySettlementReport::TYPE_ALL, $this->user->id);

        $this->assertNotNull($report->id);
        $this->assertEquals('2024-01-15', $report->report_date->format('Y-m-d'));
        $this->assertEquals(DailySettlementReport::TYPE_ALL, $report->type);
        $this->assertEquals(DailySettlementReport::STATUS_DRAFT, $report->status);
        $this->assertEquals($this->user->id, $report->generated_by);
        $this->assertNotNull($report->generated_at);
    }

    public function test_generate_for_date_returns_existing_report(): void
    {
        $existing = DailySettlementReport::factory()->create([
            'report_date' => '2024-01-15',
            'type' => DailySettlementReport::TYPE_ALL,
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]);

        $report = DailySettlementReport::generateForDate('2024-01-15', DailySettlementReport::TYPE_ALL, $this->user->id);

        $this->assertEquals($existing->id, $report->id);
    }

    public function test_generate_for_date_throws_on_locked_report(): void
    {
        $this->expectException(ReportStatusException::class);

        DailySettlementReport::factory()->create([
            'report_date' => '2024-01-15',
            'type' => DailySettlementReport::TYPE_ALL,
            'status' => DailySettlementReport::STATUS_LOCKED,
        ]);

        DailySettlementReport::generateForDate('2024-01-15', DailySettlementReport::TYPE_ALL, $this->user->id);
    }

    public function test_generate_range(): void
    {
        $reports = DailySettlementReport::generateRange('2024-01-01', '2024-01-05', DailySettlementReport::TYPE_ALL, $this->user->id);

        $this->assertCount(5, $reports);
        $this->assertEquals('2024-01-01', $reports[0]->report_date->format('Y-m-d'));
        $this->assertEquals('2024-01-05', $reports[4]->report_date->format('Y-m-d'));
    }
}
