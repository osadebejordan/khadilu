<?php

namespace App\Http\Controllers;

use App\Models\SystemAuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemAuditLog::with('user');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('action', 'like', "%{$s}%")
                  ->orWhere('details', 'like', "%{$s}%")
                  ->orWhere('table_name', 'like', "%{$s}%");
            });
        }

        $logs = $query->latest()->paginate(30)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
