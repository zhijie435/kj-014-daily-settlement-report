<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_no', 'order_id', 'created_by', 'type', 'method',
        'amount', 'currency', 'payment_date', 'transaction_no', 'remark'
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->payment_no)) {
                $prefix = $payment->type === 'income' ? 'PAY' : 'EXP';
                $payment->payment_no = $prefix . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }
}
