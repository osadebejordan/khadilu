<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'compatible_models',
        'category',
        'quality',
        'sku',
        'current_stock',
        'selling_price',
        'cost_price',
        'low_stock_threshold',
        'is_deleted',
    ];

    public static function generateSKU(string $prefix = 'KH'): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $res = '';
        for ($i = 0; $i < 8; $i++) {
            $res .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $prefix . '-' . $res;
    }

    public function getMarginPercentageAttribute(): float
    {
        if ($this->selling_price <= 0) return 0;
        $profit = $this->selling_price - $this->cost_price;
        return round(($profit / $this->selling_price) * 100, 1);
    }

    public function getMarginAmountAttribute(): float
    {
        return (float) ($this->selling_price - $this->cost_price);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->current_stock <= $this->low_stock_threshold;
    }

    public function stockInflows(): HasMany
    {
        return $this->hasMany(StockInflow::class);
    }

    public function stockOutflows(): HasMany
    {
        return $this->hasMany(StockOutflow::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockReturn::class);
    }
}
