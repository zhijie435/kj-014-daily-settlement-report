<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'sku', 'barcode', 'category_id', 'supplier_id',
        'specification', 'unit', 'cost_price', 'wholesale_price',
        'retail_price', 'agent_price', 'stock_quantity', 'safety_stock',
        'description', 'images', 'status'
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'agent_price' => 'decimal:2',
            'images' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isOnSale(): bool
    {
        return $this->status === 'on_sale';
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->safety_stock;
    }

    public function getPriceForDistributor(Distributor $distributor): float
    {
        $basePrice = $distributor->isRegionalAgent() ? $this->agent_price : $this->wholesale_price;
        return $distributor->calculateDiscountedPrice($basePrice);
    }
}
