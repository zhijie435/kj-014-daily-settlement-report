<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description', 'is_active', 'sort_order', 'settings'])]
class CustomerGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function models()
    {
        return $this->morphToMany(
            config('customer_groups.models.customer'),
            'model',
            config('customer_groups.table_names.model_has_customer_groups'),
            config('customer_groups.column_names.customer_group_pivot_key') ?? 'customer_group_id',
            config('customer_groups.column_names.model_morph_key') ?? 'model_id'
        );
    }

    protected static function booted(): void
    {
        static::saved(function () {
            app('cache')
                ->store(config('customer_groups.cache.store') != 'default' ? config('customer_groups.cache.store') : null)
                ->forget(config('customer_groups.cache.key'));
        });

        static::deleted(function () {
            app('cache')
                ->store(config('customer_groups.cache.store') != 'default' ? config('customer_groups.cache.store') : null)
                ->forget(config('customer_groups.cache.key'));
        });
    }
}
