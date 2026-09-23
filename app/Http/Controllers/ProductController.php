<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Quality;
use App\Models\StockInflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('is_deleted', false);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%")
                  ->orWhere('brand', 'like', "%{$s}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('quality')) {
            $query->where('quality', $request->quality);
        }

        if ($request->filled('stock_status')) {
            if ($request->stock_status === 'low') {
                $query->whereColumn('current_stock', '<=', 'low_stock_threshold');
            } elseif ($request->stock_status === 'out') {
                $query->where('current_stock', 0);
            } elseif ($request->stock_status === 'in') {
                $query->where('current_stock', '>', 0);
            }
        }

        $products = $query->orderBy('name', 'asc')->paginate(20)->withQueryString();
        $categories = Category::all();
        $qualities = Quality::all();

        return view('inventory.index', compact('products', 'categories', 'qualities'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'brand' => 'nullable|string|max:80',
            'compatible_models' => 'nullable|string',
            'category' => 'required|string',
            'quality' => 'required|string',
            'current_stock' => 'required|integer|min:0',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku',
        ]);

        if (empty($validated['sku'])) {
            $validated['sku'] = Product::generateSKU();
        }

        $product = Product::create($validated);

        if ($product->current_stock > 0) {
            StockInflow::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'quantity' => $product->current_stock,
                'cost_per_unit' => $product->cost_price,
                'total_cost' => $product->cost_price * $product->current_stock,
                'supplier' => 'Initial Stock',
                'notes' => 'Stock initialized upon product creation',
            ]);
        }

        logActivity('Create Product', 'products', $product->id, "Created product: {$product->name}");

        return redirect()->route('inventory.index')->with('success', 'Product created successfully!');
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'brand' => 'nullable|string|max:80',
            'compatible_models' => 'nullable|string',
            'category' => 'required|string',
            'quality' => 'required|string',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
        ]);

        $product->update($validated);
        logActivity('Update Product', 'products', $product->id, "Updated product: {$product->name}");

        return back()->with('success', 'Product updated successfully!');
    }

    public function destroy(Product $product)
    {
        $product->update(['is_deleted' => true]);
        logActivity('Delete Product', 'products', $product->id, "Soft deleted product: {$product->name}");

        return back()->with('success', 'Product removed from active inventory.');
    }

    public function lowStock()
    {
        $products = Product::where('is_deleted', false)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->orderBy('current_stock', 'asc')
            ->paginate(30);

        return view('inventory.low_stock', compact('products'));
    }

    public function bulkStockIndex()
    {
        $products = Product::where('is_deleted', false)->orderBy('name', 'asc')->get();
        return view('inventory.bulk_stock', compact('products'));
    }

    public function bulkStockStore(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_per_unit' => 'required|numeric|min:0',
            'supplier' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                if (empty($item['quantity']) || $item['quantity'] <= 0) continue;

                $product = Product::findOrFail($item['product_id']);
                $costPerUnit = (float) $item['cost_per_unit'];
                $qty = (int) $item['quantity'];
                $totalCost = $costPerUnit * $qty;

                // Update product stock and cost price
                $product->current_stock += $qty;
                if ($costPerUnit > 0) {
                    $product->cost_price = $costPerUnit;
                }
                $product->save();

                // Record inflow
                StockInflow::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'quantity' => $qty,
                    'cost_per_unit' => $costPerUnit,
                    'total_cost' => $totalCost,
                    'supplier' => $request->supplier ?? 'Bulk Supplier',
                    'notes' => $request->notes ?? 'Bulk stock inflow',
                ]);
            }
        });

        logActivity('Bulk Restock', 'products', null, 'Processed bulk stock inflow');

        return redirect()->route('inventory.index')->with('success', 'Bulk stock inflow recorded successfully!');
    }

    public function bulkPricesIndex()
    {
        $categories = Category::all();
        $products = Product::where('is_deleted', false)->orderBy('name', 'asc')->get();
        return view('inventory.bulk_prices', compact('products', 'categories'));
    }

    public function bulkPricesUpdate(Request $request)
    {
        $request->validate([
            'prices' => 'required|array',
            'prices.*.product_id' => 'required|exists:products,id',
            'prices.*.selling_price' => 'required|numeric|min:0',
            'prices.*.cost_price' => 'nullable|numeric|min:0',
        ]);

        foreach ($request->prices as $priceData) {
            $product = Product::find($priceData['product_id']);
            if ($product) {
                $product->selling_price = $priceData['selling_price'];
                if (isset($priceData['cost_price']) && $priceData['cost_price'] !== '') {
                    $product->cost_price = $priceData['cost_price'];
                }
                $product->save();
            }
        }

        logActivity('Bulk Price Update', 'products', null, 'Updated product selling/cost prices');

        return back()->with('success', 'Prices updated successfully!');
    }

    public function analyzeStock()
    {
        $products = Product::where('is_deleted', false)->get();
        $totalCostValue = $products->sum(fn($p) => $p->cost_price * $p->current_stock);
        $totalSellingValue = $products->sum(fn($p) => $p->selling_price * $p->current_stock);
        $potentialProfit = $totalSellingValue - $totalCostValue;

        $categoryStats = Product::where('is_deleted', false)
            ->select('category', DB::raw('SUM(current_stock) as total_qty'), DB::raw('SUM(current_stock * selling_price) as category_value'))
            ->groupBy('category')
            ->get();

        return view('inventory.analyze', compact('products', 'totalCostValue', 'totalSellingValue', 'potentialProfit', 'categoryStats'));
    }
}
