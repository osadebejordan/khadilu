<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'customer_name',
        'user_id',
        'total_cart_value',
        'total_paid',
        'payment_method',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outflows(): HasMany
    {
        return $this->hasMany(StockOutflow::class, 'transaction_id');
    }
}
