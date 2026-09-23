<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInflow extends Model
{
    use HasFactory;

    protected $table = 'stock_inflow';

    protected $fillable = [
        'product_id',
        'user_id',
        'quantity',
        'cost_per_unit',
        'total_cost',
        'supplier',
        'supplier_phone',
        'notes',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
