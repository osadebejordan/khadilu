<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAuditLog extends Model
{
    use HasFactory;

    protected $table = 'system_audit_log';

    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'details',
        'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
