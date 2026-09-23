<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\StockOutflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%");
        }

        $customers = $query->orderBy('name', 'asc')->paginate(20)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:20',
            'type' => 'required|in:Retail,Wholesale,Repair,Both',
        ]);

        $customer = Customer::create($validated);
        logActivity('Create Customer', 'customers', $customer->id, "Created customer: {$customer->name}");

        return back()->with('success', 'Customer added successfully.');
    }

    public function show(Customer $customer)
    {
        $outflows = StockOutflow::where('customer_id', $customer->id)->with('product')->latest()->get();
        $payments = CustomerPayment::where('customer_id', $customer->id)->with('user')->latest()->get();

        return view('customers.show', compact('customer', 'outflows', 'payments'));
    }

    public function storePayment(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'notes' => $validated['notes'] ?? null,
        ]);

        logActivity('Customer Debt Payment', 'customer_payments', $payment->id, "Received payment of ₦{$payment->amount} from {$customer->name}");

        return back()->with('success', 'Debt payment recorded successfully.');
    }
}
