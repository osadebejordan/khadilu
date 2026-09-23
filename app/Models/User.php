<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password',
        'role',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function stockInflows(): HasMany
    {
        return $this->hasMany(StockInflow::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    public function businessExpenses(): HasMany
    {
        return $this->hasMany(BusinessExpense::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SystemAuditLog::class);
    }
}
