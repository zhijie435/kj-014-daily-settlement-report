<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distributor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'company_name', 'business_license', 'type', 'region',
        'contact_person', 'phone', 'email', 'address', 'bank_name',
        'bank_account', 'credit_limit', 'balance', 'discount_rate',
        'status', 'parent_id', 'remark'
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'balance' => 'decimal:2',
            'discount_rate' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Distributor::class, 'parent_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isRegionalAgent(): bool
    {
        return $this->type === 'regional_agent';
    }

    public function isWholesaler(): bool
    {
        return $this->type === 'wholesaler';
    }

    public function calculateDiscountedPrice(float $price): float
    {
        return $price * ($this->discount_rate / 100);
    }
}
