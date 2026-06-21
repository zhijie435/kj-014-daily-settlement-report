<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_no', 'type', 'supplier_id', 'distributor_id', 'created_by',
        'subtotal', 'tax', 'discount', 'shipping', 'total', 'paid_amount',
        'payment_status', 'status', 'shipping_address', 'billing_address',
        'tracking_no', 'confirmed_at', 'shipped_at', 'delivered_at',
        'completed_at', 'remark'
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_no)) {
                $order->order_no = static::generateOrderNo($order->type);
            }
        });
    }

    public static function generateOrderNo(string $type): string
    {
        $prefixes = [
            'supplier_purchase' => 'SP',
            'distributor_order' => 'DO',
            'agent_order' => 'AO',
        ];
        $prefix = $prefixes[$type] ?? 'ORD';
        return $prefix . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getBalanceAttribute(): float
    {
        return $this->total - $this->paid_amount;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function updatePaymentStatus(): void
    {
        if ($this->paid_amount <= 0) {
            $this->payment_status = 'unpaid';
        } elseif ($this->paid_amount < $this->total) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'paid';
        }
        $this->save();
    }
}
