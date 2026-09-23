<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'type'];

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function stockOutflows(): HasMany
    {
        return $this->hasMany(StockOutflow::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockReturn::class);
    }

    public function getBalanceAttribute(): float
    {
        $outflowUnpaid = StockOutflow::where('customer_id', $this->id)
            ->sum(DB::raw('total_amount - amount_paid'));

        $payments = CustomerPayment::where('customer_id', $this->id)->sum('amount');

        $approvedReturns = StockReturn::where('customer_id', $this->id)
            ->where('status', 'Approved')
            ->sum('refund_amount');

        return (float) ($outflowUnpaid - $payments - $approvedReturns);
    }
}
