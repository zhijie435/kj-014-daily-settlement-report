<?php

namespace App\Exceptions;

class ReportGenerateException extends BusinessException
{
    protected int $statusCode = 500;

    protected string $errorCode = 'report_generate_error';

    public static function dateRangeTooLarge(int $days, int $maxDays): self
    {
        return new self("日期范围过大，最多支持生成 {$maxDays} 天的报表，当前选择了 {$days} 天");
    }

    public static function futureDate(string $date): self
    {
        return new self("不能生成未来日期的报表：{$date}");
    }

    public static function generationFailed(string $date, string $reason): self
    {
        return new self("生成 {$date} 的报表失败：{$reason}");
    }
}
