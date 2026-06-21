<?php

namespace Tests\Feature;

use App\Models\DailySettlementReport;
use App\Models\User;
use App\Services\DailySettlementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailySettlementReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Role $platformRole;

    protected function setUp(): void
    {
        parent::setUp();

        $guardName = config('auth.defaults.guard');

        $permissions = [
            'report.view', 'report.manage', 'report.generate', 'report.regenerate',
            'report.confirm', 'report.audit', 'report.lock', 'report.export',
            'report.delete',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => $guardName]);
        }

        $this->platformRole = Role::firstOrCreate(['name' => 'platform', 'guard_name' => $guardName]);
        $this->platformRole->syncPermissions($permissions);

        $this->user = User::factory()->create(['user_type' => 'platform']);
        $this->user->assignRole('platform');

        $this->app->bind(DailySettlementReportService::class, function () {
            return new DailySettlementReportService($this->user);
        });

        Sanctum::actingAs($this->user, ['*']);
    }

    protected function createReports(int $count, string $startDate = '2024-01-01'): void
    {
        $start = \Carbon\Carbon::parse($startDate);
        for ($i = 0; $i < $count; $i++) {
            DailySettlementReport::factory()->create([
                'report_date' => $start->copy()->addDays($i)->format('Y-m-d'),
            ]);
        }
    }

    public function test_index_returns_paginated_reports(): void
    {
        $this->createReports(15);

        $response = $this->getJson('/api/daily-settlement-reports?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
                'last_page',
            ]);

        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(15, $response->json('total'));
    }

    public function test_index_with_filters(): void
    {
        $this->createReports(3, '2024-01-10');

        $response = $this->getJson('/api/daily-settlement-reports?' . http_build_query([
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'type' => DailySettlementReport::TYPE_ALL,
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]));

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('total'));
    }

    public function test_show_returns_report_detail(): void
    {
        $report = DailySettlementReport::factory()->create([
            'generated_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/daily-settlement-reports/{$report->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'report_date',
                'type',
                'status',
                'status_label',
                'orders',
                'amounts',
                'payments',
                'remark',
                'can',
            ]);
    }

    public function test_store_creates_report(): void
    {
        $reportDate = now()->subDay()->format('Y-m-d');

        $response = $this->postJson('/api/daily-settlement-reports', [
            'report_date' => $reportDate,
            'type' => DailySettlementReport::TYPE_ALL,
            'remark' => 'Test remark',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '日结算报表生成成功',
            ])
            ->assertJsonStructure([
                'message',
                'report' => [
                    'id',
                    'report_date',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('daily_settlement_reports', [
            'report_date' => $reportDate,
            'type' => DailySettlementReport::TYPE_ALL,
            'remark' => 'Test remark',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/daily-settlement-reports', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['report_date']);
    }

    public function test_store_validates_invalid_type(): void
    {
        $response = $this->postJson('/api/daily-settlement-reports', [
            'report_date' => now()->subDay()->format('Y-m-d'),
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_returns_error_for_future_date(): void
    {
        $futureDate = now()->addDay()->format('Y-m-d');

        $response = $this->postJson('/api/daily-settlement-reports', [
            'report_date' => $futureDate,
            'type' => DailySettlementReport::TYPE_ALL,
        ]);

        $response->assertStatus(500)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_generate_batch_creates_multiple_reports(): void
    {
        $dateFrom = now()->subDays(5)->format('Y-m-d');
        $dateTo = now()->subDay()->format('Y-m-d');

        $response = $this->postJson('/api/daily-settlement-reports/generate-batch', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'type' => DailySettlementReport::TYPE_ALL,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '批量生成日结算报表成功',
                'generated_count' => 5,
            ]);

        $this->assertCount(5, DailySettlementReport::all());
    }

    public function test_generate_batch_validates_date_range(): void
    {
        $response = $this->postJson('/api/daily-settlement-reports/generate-batch', [
            'date_from' => '2024-02-01',
            'date_to' => '2024-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_generate_batch_returns_error_for_large_range(): void
    {
        $dateFrom = now()->subDays(100)->format('Y-m-d');
        $dateTo = now()->subDay()->format('Y-m-d');

        $response = $this->postJson('/api/daily-settlement-reports/generate-batch', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'type' => DailySettlementReport::TYPE_ALL,
        ]);

        $response->assertStatus(500)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_regenerate_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'report_date' => now()->subDay()->format('Y-m-d'),
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/regenerate");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '日结算报表重新生成成功',
            ])
            ->assertJsonStructure(['report']);
    }

    public function test_regenerate_locked_report_fails(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_LOCKED,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/regenerate");

        $response->assertStatus(400)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_update_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
            'remark' => 'old remark',
        ]);

        $response = $this->putJson("/api/daily-settlement-reports/{$report->id}", [
            'remark' => 'new remark',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '日结算报表更新成功',
            ]);

        $this->assertDatabaseHas('daily_settlement_reports', [
            'id' => $report->id,
            'remark' => 'new remark',
        ]);
    }

    public function test_update_audited_report_fails(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_AUDITED,
        ]);

        $response = $this->putJson("/api/daily-settlement-reports/{$report->id}", [
            'remark' => 'test',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_destroy_deletes_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]);

        $response = $this->deleteJson("/api/daily-settlement-reports/{$report->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => '日结算报表删除成功']);

        $this->assertSoftDeleted($report);
    }

    public function test_destroy_confirmed_report_fails(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
        ]);

        $response = $this->deleteJson("/api/daily-settlement-reports/{$report->id}");

        $response->assertStatus(400)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_confirm_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/confirm");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '日结算报表确认成功',
            ]);

        $report->refresh();
        $this->assertTrue($report->isConfirmed());
    }

    public function test_confirm_confirmed_report_fails(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/confirm");

        $response->assertStatus(400)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_audit_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/audit");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '日结算报表审核成功',
            ]);

        $report->refresh();
        $this->assertTrue($report->isAudited());
    }

    public function test_audit_draft_report_fails(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_DRAFT,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/audit");

        $response->assertStatus(400)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_lock_report(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_AUDITED,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/lock");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '日结算报表锁定成功',
            ]);

        $report->refresh();
        $this->assertTrue($report->isLocked());
    }

    public function test_lock_confirmed_report_fails(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/lock");

        $response->assertStatus(400)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_revert_to_draft(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_CONFIRMED,
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/revert-to-draft");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '日结算报表已退回草稿',
            ]);

        $report->refresh();
        $this->assertTrue($report->isDraft());
        $this->assertNull($report->confirmed_by);
    }

    public function test_revert_to_draft_from_audited_fails(): void
    {
        $report = DailySettlementReport::factory()->create([
            'status' => DailySettlementReport::STATUS_AUDITED,
        ]);

        $response = $this->postJson("/api/daily-settlement-reports/{$report->id}/revert-to-draft");

        $response->assertStatus(400)
            ->assertJsonStructure(['message', 'error_code']);
    }

    public function test_summary_returns_stats(): void
    {
        $this->createReports(3, '2024-01-10');

        $response = $this->getJson('/api/daily-settlement-reports/summary?' . http_build_query([
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date_range',
                'total_days',
                'total_orders',
                'total_amount',
                'net_profit',
                'payment_methods',
            ]);
    }

    public function test_export_returns_csv(): void
    {
        $this->createReports(3, '2024-01-10');

        $response = $this->get('/api/daily-settlement-reports/export?' . http_build_query([
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'format' => 'csv',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_not_found_report(): void
    {
        $response = $this->getJson('/api/daily-settlement-reports/9999');

        $response->assertStatus(404);
    }
}
