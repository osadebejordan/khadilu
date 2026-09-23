<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOutflow extends Model
{
    use HasFactory;

    protected $table = 'stock_outflow';

    protected $fillable = [
        'transaction_id',
        'product_id',
        'user_id',
        'quantity',
        'cost_price',
        'price_per_unit',
        'total_amount',
        'amount_paid',
        'customer_id',
        'customer_name',
        'payment_method',
        'notes',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'transaction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
