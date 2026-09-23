<?php

namespace App\Http\Controllers;

use App\Models\StockOutflow;
use App\Models\StockInflow;
use App\Models\BusinessExpense;
use App\Models\CustomerPayment;
use App\Models\Repair;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function closingReport(Request $request)
    {
        $dateStr = $request->get('date', Carbon::today()->toDateString());
        $targetDate = Carbon::parse($dateStr);

        // Sales for selected date
        $sales = StockOutflow::whereDate('created_at', $targetDate)->with(['product', 'user', 'customer'])->get();

        $totalSalesCount = $sales->count();
        $totalSalesRevenue = $sales->sum('total_amount');
        $totalSalesCost = $sales->sum(DB::raw('cost_price * quantity'));
        $grossSalesProfit = $totalSalesRevenue - $totalSalesCost;

        // Payment method breakdown
        $cashSales = $sales->where('payment_method', 'Cash')->sum('total_amount');
        $transferSales = $sales->where('payment_method', 'Transfer')->sum('total_amount');
        $posSales = $sales->where('payment_method', 'POS')->sum('total_amount');
        $otherSales = $sales->where('payment_method', 'Other')->sum('total_amount');

        // Customer Debt Payments received on this date
        $debtPayments = CustomerPayment::whereDate('created_at', $targetDate)->with('customer')->get();
        $totalDebtCollected = $debtPayments->sum('amount');

        // Repairs logged/paid on this date
        $repairs = Repair::whereDate('created_at', $targetDate)->get();
        $totalRepairIncome = $repairs->sum('amount_paid');

        // Business Expenses on this date
        $expenses = BusinessExpense::whereDate('expense_date', $targetDate)->get();
        $totalExpenses = $expenses->sum('amount');

        // Net Cash/Revenue calculation
        $totalCashIn = $cashSales + $totalDebtCollected + $totalRepairIncome;
        $netProfit = $grossSalesProfit - $totalExpenses;

        return view('reports.closing', compact(
            'targetDate',
            'sales',
            'totalSalesCount',
            'totalSalesRevenue',
            'grossSalesProfit',
            'cashSales',
            'transferSales',
            'posSales',
            'otherSales',
            'debtPayments',
            'totalDebtCollected',
            'repairs',
            'totalRepairIncome',
            'expenses',
            'totalExpenses',
            'totalCashIn',
            'netProfit'
        ));
    }
}
