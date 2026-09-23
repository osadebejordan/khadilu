<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Customer;
use App\Models\SalesTransaction;
use App\Models\StockOutflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesController extends Controller
{
    public function create()
    {
        $products = Product::where('is_deleted', false)
            ->where('current_stock', '>', 0)
            ->orderBy('name', 'asc')
            ->get();

        $customers = Customer::orderBy('name', 'asc')->get();

        return view('sales.pos', compact('products', 'customers'));
    }

    public function searchProducts(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $products = Product::where('is_deleted', false)
            ->where('current_stock', '>', 0)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhere('brand', 'like', "%{$q}%");
            })
            ->take(15)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'brand' => $p->brand,
                    'category' => $p->category,
                    'quality' => $p->quality,
                    'sku' => $p->sku,
                    'selling_price' => (float) $p->selling_price,
                    'formatted_price' => money($p->selling_price),
                    'current_stock' => $p->current_stock,
                ];
            });

        return response()->json($products);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:120',
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'required|in:Cash,Transfer,POS,Other',
            'amount_paid' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'cart' => 'required|array|min:1',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.quantity' => 'required|integer|min:1',
            'cart.*.price' => 'required|numeric|min:0',
        ]);

        $transactionId = DB::transaction(function () use ($validated) {
            $totalCartValue = 0;
            foreach ($validated['cart'] as $item) {
                $totalCartValue += ($item['price'] * $item['quantity']);
            }

            $transaction = SalesTransaction::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'],
                'user_id' => Auth::id(),
                'total_cart_value' => $totalCartValue,
                'total_paid' => $validated['amount_paid'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $totalItemsCount = count($validated['cart']);

            foreach ($validated['cart'] as $index => $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->current_stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                // Deduct stock
                $product->current_stock -= $item['quantity'];
                $product->save();

                // Pro-rate payment for this item if customer paid partial debt
                $itemTotal = $item['price'] * $item['quantity'];
                $itemPaid = ($totalCartValue > 0) ? ($itemTotal / $totalCartValue) * $validated['amount_paid'] : 0;

                StockOutflow::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'quantity' => $item['quantity'],
                    'cost_price' => $product->cost_price,
                    'price_per_unit' => $item['price'],
                    'total_amount' => $itemTotal,
                    'amount_paid' => $itemPaid,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'customer_name' => $validated['customer_name'],
                    'payment_method' => $validated['payment_method'],
                    'notes' => $validated['notes'] ?? null,
                ]);
            }

            logActivity('Sales Transaction', 'sales_transactions', $transaction->id, "Completed sale #{$transaction->id} for {$transaction->customer_name}");

            return $transaction->id;
        });

        return response()->json([
            'success' => true,
            'transaction_id' => $transactionId,
            'redirect_url' => route('sales.receipt', $transactionId),
        ]);
    }

    public function showReceipt($id)
    {
        $transaction = SalesTransaction::with(['outflows.product', 'user', 'customer'])->findOrFail($id);
        return view('sales.receipt', compact('transaction'));
    }

    public function history(Request $request)
    {
        $query = StockOutflow::with(['product', 'user', 'customer']);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        } elseif ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('customer_name', 'like', "%{$s}%")
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"));
            });
        }

        $outflows = $query->latest()->paginate(25)->withQueryString();

        $totalRevenue = $query->sum('total_amount');
        $totalCost = $query->sum(DB::raw('cost_price * quantity'));
        $grossProfit = $totalRevenue - $totalCost;

        return view('sales.history', compact('outflows', 'totalRevenue', 'totalCost', 'grossProfit'));
    }
}
