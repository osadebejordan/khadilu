<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockOutflow;
use App\Models\StockInflow;
use App\Models\Repair;
use App\Models\BusinessExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Revenue today
        $todayRevenue = StockOutflow::whereDate('created_at', $today)->sum('total_amount');

        // Revenue this month
        $monthRevenue = StockOutflow::where('created_at', '>=', $startOfMonth)->sum('total_amount');

        // Total inventory asset value (based on selling price and cost price)
        $products = Product::where('is_deleted', false)->get();
        $totalSellingValue = $products->sum(fn($p) => $p->selling_price * $p->current_stock);
        $totalCostValue = $products->sum(fn($p) => $p->cost_price * $p->current_stock);

        // Low stock products
        $lowStockProducts = Product::where('is_deleted', false)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->orderBy('current_stock', 'asc')
            ->get();

        // Recent Sales
        $recentSales = StockOutflow::with(['product', 'user'])
            ->latest()
            ->take(8)
            ->get();

        // Pending Repairs count
        $pendingRepairsCount = Repair::whereIn('status', ['Received', 'In-Progress'])->count();

        // Monthly profit estimation
        $monthCostOfGoods = StockOutflow::where('created_at', '>=', $startOfMonth)->sum(DB::raw('cost_price * quantity'));
        $monthExpenses = BusinessExpense::where('created_at', '>=', $startOfMonth)->sum('amount');
        $estimatedMonthProfit = $monthRevenue - $monthCostOfGoods - $monthExpenses;

        return view('dashboard', compact(
            'todayRevenue',
            'monthRevenue',
            'totalSellingValue',
            'totalCostValue',
            'lowStockProducts',
            'recentSales',
            'pendingRepairsCount',
            'estimatedMonthProfit'
        ));
    }
}
