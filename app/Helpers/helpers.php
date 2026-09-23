<?php

use App\Models\SystemAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

if (!function_exists('money')) {
    function money(float|int|string $amount): string
    {
        $symbol = config('app.currency', '₦');
        return $symbol . number_format((float) $amount, 2);
    }
}

if (!function_exists('shortMoney')) {
    function shortMoney(float|int|string $amount): string
    {
        $symbol = config('app.currency', '₦');
        $amount = (float) str_replace(',', '', (string) $amount);

        return match (true) {
            $amount >= 1000000000 => $symbol . number_format($amount / 1000000000, 1) . 'B',
            $amount >= 1000000    => $symbol . number_format($amount / 1000000, 1) . 'M',
            $amount >= 1000       => $symbol . (($val = $amount / 1000) == (int) $val ? number_format($val, 0) : number_format($val, 1)) . 'K',
            default               => $symbol . number_format($amount, 0),
        };
    }
}

if (!function_exists('calculateMargin')) {
    function calculateMargin(float|int $cost, float|int $selling): float|int
    {
        if ($selling <= 0) return 0;
        $profit = $selling - $cost;
        return round(($profit / $selling) * 100, 1);
    }
}

if (!function_exists('logActivity')) {
    function logActivity(string $action, ?string $table = null, ?int $recordId = null, ?string $details = null): void
    {
        $userId = Auth::id();
        if (!$userId) return;

        try {
            SystemAuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'details' => $details,
                'ip_address' => Request::ip() ?? '127.0.0.1',
            ]);
        } catch (\Throwable $e) {
            // Fail silently if audit fails
        }
    }
}
