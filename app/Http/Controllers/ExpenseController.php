<?php

namespace App\Http\Controllers;

use App\Models\BusinessExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = BusinessExpense::with('user');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('month')) {
            $query->whereMonth('expense_date', date('m', strtotime($request->month)))
                  ->whereYear('expense_date', date('Y', strtotime($request->month)));
        }

        $expenses = $query->latest('expense_date')->paginate(20)->withQueryString();
        $totalExpenses = $query->sum('amount');

        return view('expenses.index', compact('expenses', 'totalExpenses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:Rent,Electricity,Salary,Marketing,Transport,Others',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'expense_date' => 'required|date',
        ]);

        $validated['user_id'] = Auth::id();

        $expense = BusinessExpense::create($validated);
        logActivity('Log Expense', 'business_expenses', $expense->id, "Recorded expense of ₦{$expense->amount} for {$expense->category}");

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function destroy(BusinessExpense $expense)
    {
        logActivity('Delete Expense', 'business_expenses', $expense->id, "Deleted expense of ₦{$expense->amount}");
        $expense->delete();

        return back()->with('success', 'Expense record deleted.');
    }
}
