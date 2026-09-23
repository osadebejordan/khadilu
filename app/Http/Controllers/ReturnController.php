<?php

namespace App\Http\Controllers;

use App\Models\StockReturn;
use App\Models\Product;
use App\Models\Customer;
use App\Models\StockOutflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = StockReturn::with(['product', 'customer', 'user'])->latest()->paginate(20);
        $products = Product::where('is_deleted', false)->orderBy('name', 'asc')->get();
        $customers = Customer::orderBy('name', 'asc')->get();

        return view('returns.index', compact('returns', 'products', 'customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'customer_id' => 'nullable|exists:customers,id',
            'quantity' => 'required|integer|min:1',
            'refund_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'Pending';

        $returnItem = StockReturn::create($validated);
        logActivity('Log Stock Return', 'stock_returns', $returnItem->id, 'Created stock return request');

        return back()->with('success', 'Return request logged (Status: Pending Approval).');
    }

    public function updateStatus(Request $request, StockReturn $returnItem)
    {
        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected',
        ]);

        DB::transaction(function () use ($returnItem, $validated) {
            $oldStatus = $returnItem->status;
            $newStatus = $validated['status'];

            $returnItem->status = $newStatus;
            $returnItem->save();

            // If approving return, restore product inventory
            if ($oldStatus !== 'Approved' && $newStatus === 'Approved') {
                $product = Product::findOrFail($returnItem->product_id);
                $product->current_stock += $returnItem->quantity;
                $product->save();
            }

            logActivity('Stock Return Status', 'stock_returns', $returnItem->id, "Return #{$returnItem->id} marked as {$newStatus}");
        });

        return back()->with('success', "Stock return marked as {$validated['status']}.");
    }
}
