<?php

namespace App\Models;

use App\Exceptions\ReportStatusException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class DailySettlementReport extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT = 'draft';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_AUDITED = 'audited';
    const STATUS_LOCKED = 'locked';

    const TYPE_SUPPLIER_PURCHASE = 'supplier_purchase';
    const TYPE_DISTRIBUTOR_ORDER = 'distributor_order';
    const TYPE_AGENT_ORDER = 'agent_order';
    const TYPE_ALL = 'all';

    const MAX_GENERATE_DAYS = 90;

    protected $fillable = [
        'report_date', 'type', 'status',
        'total_orders', 'purchase_orders', 'distributor_orders', 'agent_orders',
        'completed_orders', 'pending_orders',
        'total_amount', 'purchase_amount', 'sales_amount', 'paid_amount', 'unpaid_amount',
        'total_income', 'total_expense', 'net_profit',
        'cash_income', 'bank_transfer_income', 'alipay_income', 'wechat_income',
        'remark', 'generated_by', 'generated_at',
        'confirmed_by', 'confirmed_at',
        'audited_by', 'audited_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'generated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'audited_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'purchase_amount' => 'decimal:2',
            'sales_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'unpaid_amount' => 'decimal:2',
            'total_income' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'cash_income' => 'decimal:2',
            'bank_transfer_income' => 'decimal:2',
            'alipay_income' => 'decimal:2',
            'wechat_income' => 'decimal:2',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function auditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if ($dateFrom) {
            $query->whereDate('report_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('report_date', '<=', $dateTo);
        }
        return $query;
    }

    public function scopeByType(Builder $query, ?string $type): Builder
    {
        if ($type && $type !== self::TYPE_ALL) {
            $query->where('type', $type);
        }
        return $query;
    }

    public function scopeOrderByDate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('report_date', $direction);
    }

    public function setReportDateAttribute($value): void
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            $this->attributes['report_date'] = $value->format('Y-m-d');
        } else {
            $this->attributes['report_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
        }
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isAudited(): bool
    {
        return $this->status === self::STATUS_AUDITED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONFIRMED], true);
    }

    public function canRegenerate(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONFIRMED], true);
    }

    public function canDelete(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT], true);
    }

    public function canConfirm(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canAudit(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function canLock(): bool
    {
        return $this->status === self::STATUS_AUDITED;
    }

    public function canRevertToDraft(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED], true);
    }

    public function confirm(int $userId): self
    {
        if (!$this->canConfirm()) {
            throw ReportStatusException::invalidTransition($this->status, self::STATUS_CONFIRMED);
        }

        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
        ]);

        return $this;
    }

    public function audit(int $userId): self
    {
        if (!$this->canAudit()) {
            throw ReportStatusException::invalidTransition($this->status, self::STATUS_AUDITED);
        }

        $this->update([
            'status' => self::STATUS_AUDITED,
            'audited_by' => $userId,
            'audited_at' => now(),
        ]);

        return $this;
    }

    public function lock(): self
    {
        if (!$this->canLock()) {
            throw ReportStatusException::invalidTransition($this->status, self::STATUS_LOCKED);
        }

        $this->update(['status' => self::STATUS_LOCKED]);

        return $this;
    }

    public function revertToDraft(int $userId): self
    {
        if (!$this->canRevertToDraft()) {
            throw ReportStatusException::invalidTransition($this->status, self::STATUS_DRAFT);
        }

        $this->update([
            'status' => self::STATUS_DRAFT,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ]);

        return $this;
    }

    public static function generateForDate($date, $type = self::TYPE_ALL, $userId = null): self
    {
        $reportDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
        $startDate = \Carbon\Carbon::parse($date)->startOfDay();
        $endDate = \Carbon\Carbon::parse($date)->endOfDay();

        $report = self::withTrashed()
            ->whereDate('report_date', $reportDate)
            ->where('type', $type)
            ->first();

        if (!$report) {
            $report = new self(['report_date' => $reportDate, 'type' => $type, 'status' => self::STATUS_DRAFT]);
        } elseif ($report->trashed()) {
            $report->restore();
            $report->status = self::STATUS_DRAFT;
        }

        if (!$report->canRegenerate()) {
            throw ReportStatusException::notRegeneratable($report->status);
        }

        $orderStats = self::calculateOrderStats($startDate, $endDate, $type);
        $paymentStats = self::calculatePaymentStats($startDate, $endDate, $type);

        $income = $paymentStats['income'];
        $expense = $paymentStats['expense'];
        $totalAmount = $orderStats['total_amount'];

        if ($type === self::TYPE_SUPPLIER_PURCHASE) {
            $paidAmount = $expense;
            $unpaidAmount = $totalAmount - $expense;
        } else {
            $paidAmount = $income;
            $unpaidAmount = $totalAmount - $income;
        }

        $report->fill([
            'total_orders' => $orderStats['total_orders'],
            'purchase_orders' => $orderStats['purchase_orders'],
            'distributor_orders' => $orderStats['distributor_orders'],
            'agent_orders' => $orderStats['agent_orders'],
            'completed_orders' => $orderStats['completed_orders'],
            'pending_orders' => $orderStats['pending_orders'],
            'total_amount' => $totalAmount,
            'purchase_amount' => $orderStats['purchase_amount'],
            'sales_amount' => $orderStats['sales_amount'],
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $unpaidAmount,
            'total_income' => $income,
            'total_expense' => $expense,
            'net_profit' => $income - $expense,
            'cash_income' => $paymentStats['cash_income'],
            'bank_transfer_income' => $paymentStats['bank_income'],
            'alipay_income' => $paymentStats['alipay_income'],
            'wechat_income' => $paymentStats['wechat_income'],
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);

        $report->save();

        return $report;
    }

    public static function generateRange($dateFrom, $dateTo, $type = self::TYPE_ALL, $userId = null): array
    {
        $start = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);
        $reports = [];

        $current = $start->copy();
        while ($current->lte($end)) {
            $reports[] = self::generateForDate($current, $type, $userId);
            $current->addDay();
        }

        return $reports;
    }

    protected static function calculateOrderStats($startDate, $endDate, $type): array
    {
        $query = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled');

        if ($type !== self::TYPE_ALL) {
            $query->where('type', $type);
        }

        $stats = $query
            ->select(
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN type = "supplier_purchase" THEN 1 ELSE 0 END) as purchase_orders'),
                DB::raw('SUM(CASE WHEN type = "distributor_order" THEN 1 ELSE 0 END) as distributor_orders'),
                DB::raw('SUM(CASE WHEN type = "agent_order" THEN 1 ELSE 0 END) as agent_orders'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_orders'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_orders'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('SUM(CASE WHEN type = "supplier_purchase" THEN total ELSE 0 END) as purchase_amount'),
                DB::raw('SUM(CASE WHEN type != "supplier_purchase" THEN total ELSE 0 END) as sales_amount'),
            )
            ->first();

        return [
            'total_orders' => (int) ($stats?->total_orders ?? 0),
            'purchase_orders' => (int) ($stats?->purchase_orders ?? 0),
            'distributor_orders' => (int) ($stats?->distributor_orders ?? 0),
            'agent_orders' => (int) ($stats?->agent_orders ?? 0),
            'completed_orders' => (int) ($stats?->completed_orders ?? 0),
            'pending_orders' => (int) ($stats?->pending_orders ?? 0),
            'total_amount' => (float) ($stats?->total_amount ?? 0),
            'purchase_amount' => (float) ($stats?->purchase_amount ?? 0),
            'sales_amount' => (float) ($stats?->sales_amount ?? 0),
        ];
    }

    protected static function calculatePaymentStats($startDate, $endDate, $type): array
    {
        $query = Payment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('order', function ($q) {
                $q->where('status', '!=', 'cancelled');
            });

        if ($type !== self::TYPE_ALL) {
            $query->whereHas('order', function ($q) use ($type) {
                $q->where('type', $type);
            });
        }

        $stats = $query
            ->select(
                DB::raw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income'),
                DB::raw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "cash" THEN amount ELSE 0 END) as cash_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "bank_transfer" THEN amount ELSE 0 END) as bank_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "alipay" THEN amount ELSE 0 END) as alipay_income'),
                DB::raw('SUM(CASE WHEN type = "income" AND method = "wechat" THEN amount ELSE 0 END) as wechat_income'),
            )
            ->first();

        return [
            'income' => (float) ($stats?->income ?? 0),
            'expense' => (float) ($stats?->expense ?? 0),
            'cash_income' => (float) ($stats?->cash_income ?? 0),
            'bank_income' => (float) ($stats?->bank_income ?? 0),
            'alipay_income' => (float) ($stats?->alipay_income ?? 0),
            'wechat_income' => (float) ($stats?->wechat_income ?? 0),
        ];
    }

    public function toArrayResponse(): array
    {
        return [
            'id' => $this->id,
            'report_date' => $this->report_date->format('Y-m-d'),
            'type' => $this->type,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'orders' => [
                'total' => $this->total_orders,
                'purchase' => $this->purchase_orders,
                'distributor' => $this->distributor_orders,
                'agent' => $this->agent_orders,
                'completed' => $this->completed_orders,
                'pending' => $this->pending_orders,
            ],
            'amounts' => [
                'total' => $this->total_amount,
                'purchase' => $this->purchase_amount,
                'sales' => $this->sales_amount,
                'paid' => $this->paid_amount,
                'unpaid' => $this->unpaid_amount,
            ],
            'payments' => [
                'income' => $this->total_income,
                'expense' => $this->total_expense,
                'net' => $this->net_profit,
                'by_method' => [
                    'cash' => $this->cash_income,
                    'bank_transfer' => $this->bank_transfer_income,
                    'alipay' => $this->alipay_income,
                    'wechat' => $this->wechat_income,
                ],
            ],
            'remark' => $this->remark,
            'generated_at' => $this->generated_at?->format('Y-m-d H:i:s'),
            'generated_by' => $this->generatedBy?->name,
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s'),
            'confirmed_by' => $this->confirmedBy?->name,
            'audited_at' => $this->audited_at?->format('Y-m-d H:i:s'),
            'audited_by' => $this->auditedBy?->name,
            'can' => [
                'edit' => $this->canEdit(),
                'regenerate' => $this->canRegenerate(),
                'delete' => $this->canDelete(),
                'confirm' => $this->canConfirm(),
                'audit' => $this->canAudit(),
                'lock' => $this->canLock(),
                'revert_to_draft' => $this->canRevertToDraft(),
            ],
        ];
    }

    protected function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_CONFIRMED => '已确认',
            self::STATUS_AUDITED => '已审核',
            self::STATUS_LOCKED => '已锁定',
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
