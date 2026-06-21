<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory';

    protected $fillable = [
        'product_id', 'supplier_id', 'quantity', 'available_quantity',
        'reserved_quantity', 'unit_cost', 'batch_no', 'expiry_date',
        'location', 'remark'
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isAvailable(): bool
    {
        return $this->available_quantity > 0;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
