<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Repair extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'customer_name',
        'customer_phone',
        'device_model',
        'category',
        'issue_description',
        'estimated_cost',
        'amount_paid',
        'status',
        'notes',
        'user_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
