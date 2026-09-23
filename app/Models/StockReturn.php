<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'customer_id',
        'outflow_id',
        'user_id',
        'quantity',
        'refund_amount',
        'notes',
        'status',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function outflow(): BelongsTo
    {
        return $this->belongsTo(StockOutflow::class, 'outflow_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
