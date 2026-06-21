<?php

namespace Tests\Unit;

use App\Exceptions\ReportGenerateException;
use App\Exceptions\ReportStatusException;
use App\Exceptions\BusinessException;
use Tests\TestCase;

class ReportExceptionsTest extends TestCase
{
    public function test_report_generate_exception_extends_business_exception(): void
    {
        $exception = new ReportGenerateException('test');
        $this->assertInstanceOf(BusinessException::class, $exception);
    }

    public function test_report_generate_exception_default_status_code(): void
    {
        $exception = new ReportGenerateException('test');
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function test_report_generate_exception_default_error_code(): void
    {
        $exception = new ReportGenerateException('test');
        $this->assertEquals('report_generate_error', $exception->getErrorCode());
    }

    public function test_date_range_too_large_exception(): void
    {
        $exception = ReportGenerateException::dateRangeTooLarge(100, 90);

        $this->assertStringContainsString('100', $exception->getMessage());
        $this->assertStringContainsString('90', $exception->getMessage());
        $this->assertStringContainsString('日期范围过大', $exception->getMessage());
    }

    public function test_future_date_exception(): void
    {
        $date = '2025-01-01';
        $exception = ReportGenerateException::futureDate($date);

        $this->assertStringContainsString($date, $exception->getMessage());
        $this->assertStringContainsString('不能生成未来日期的报表', $exception->getMessage());
    }

    public function test_generation_failed_exception(): void
    {
        $date = '2024-01-01';
        $reason = 'database error';
        $exception = ReportGenerateException::generationFailed($date, $reason);

        $this->assertStringContainsString($date, $exception->getMessage());
        $this->assertStringContainsString($reason, $exception->getMessage());
        $this->assertStringContainsString('失败', $exception->getMessage());
    }

    public function test_report_generate_exception_render(): void
    {
        $exception = ReportGenerateException::futureDate('2025-01-01');
        $response = $exception->render();

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(500, $response->status());

        $data = $response->getData(true);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('error_code', $data);
        $this->assertEquals('report_generate_error', $data['error_code']);
    }

    public function test_report_status_exception_extends_business_exception(): void
    {
        $exception = new ReportStatusException('test');
        $this->assertInstanceOf(BusinessException::class, $exception);
    }

    public function test_report_status_exception_default_status_code(): void
    {
        $exception = new ReportStatusException('test');
        $this->assertEquals(400, $exception->getStatusCode());
    }

    public function test_report_status_exception_default_error_code(): void
    {
        $exception = new ReportStatusException('test');
        $this->assertEquals('report_status_error', $exception->getErrorCode());
    }

    public function test_invalid_transition_exception(): void
    {
        $exception = ReportStatusException::invalidTransition('draft', 'locked');

        $this->assertStringContainsString('draft', $exception->getMessage());
        $this->assertStringContainsString('locked', $exception->getMessage());
        $this->assertStringContainsString('无法将报表', $exception->getMessage());
    }

    public function test_not_editable_exception(): void
    {
        $exception = ReportStatusException::notEditable('audited');

        $this->assertStringContainsString('audited', $exception->getMessage());
        $this->assertStringContainsString('不允许编辑', $exception->getMessage());
    }

    public function test_not_deletable_exception(): void
    {
        $exception = ReportStatusException::notDeletable('confirmed');

        $this->assertStringContainsString('confirmed', $exception->getMessage());
        $this->assertStringContainsString('不允许删除', $exception->getMessage());
    }

    public function test_not_regeneratable_exception(): void
    {
        $exception = ReportStatusException::notRegeneratable('locked');

        $this->assertStringContainsString('locked', $exception->getMessage());
        $this->assertStringContainsString('不允许重新生成', $exception->getMessage());
    }

    public function test_report_status_exception_render(): void
    {
        $exception = ReportStatusException::notEditable('audited');
        $response = $exception->render();

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $this->assertEquals(400, $response->status());

        $data = $response->getData(true);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('error_code', $data);
        $this->assertEquals('report_status_error', $data['error_code']);
    }

    public function test_business_exception_constructor(): void
    {
        $exception = new class ('test message', 123) extends BusinessException {
        };

        $this->assertEquals('test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
    }
}
