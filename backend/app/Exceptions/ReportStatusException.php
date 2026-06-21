<?php

namespace App\Exceptions;

class ReportStatusException extends BusinessException
{
    protected int $statusCode = 400;

    protected string $errorCode = 'report_status_error';

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("无法将报表从「{$from}」状态变更为「{$to}」状态");
    }

    public static function notEditable(string $status): self
    {
        return new self("报表当前状态为「{$status}」，不允许编辑");
    }

    public static function notDeletable(string $status): self
    {
        return new self("报表当前状态为「{$status}」，不允许删除");
    }

    public static function notRegeneratable(string $status): self
    {
        return new self("报表当前状态为「{$status}」，不允许重新生成");
    }
}
