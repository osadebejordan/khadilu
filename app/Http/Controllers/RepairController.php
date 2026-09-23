<?php

namespace App\Http\Controllers;

use App\Models\Repair;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RepairController extends Controller
{
    public function index(Request $request)
    {
        $query = Repair::with(['customer', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('customer_name', 'like', "%{$s}%")
                  ->orWhere('device_model', 'like', "%{$s}%")
                  ->orWhere('issue_description', 'like', "%{$s}%");
            });
        }

        $repairs = $query->latest()->paginate(20)->withQueryString();
        $customers = Customer::orderBy('name', 'asc')->get();

        return view('repairs.index', compact('repairs', 'customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:30',
            'customer_id' => 'nullable|exists:customers,id',
            'device_model' => 'required|string|max:150',
            'category' => 'required|string|max:100',
            'issue_description' => 'required|string',
            'estimated_cost' => 'required|numeric|min:0',
            'amount_paid' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'Received';

        $repair = Repair::create($validated);
        logActivity('Create Repair Ticket', 'repairs', $repair->id, "Log repair ticket for {$repair->device_model}");

        return back()->with('success', 'Repair job created successfully.');
    }

    public function show(Repair $repair)
    {
        $repair->load(['customer', 'user', 'product']);
        return view('repairs.show', compact('repair'));
    }

    public function updateStatus(Request $request, Repair $repair)
    {
        $validated = $request->validate([
            'status' => 'required|in:Received,In-Progress,Ready,Delivered,Cancelled',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $repair->status = $validated['status'];
        if (isset($validated['amount_paid'])) {
            $repair->amount_paid = $validated['amount_paid'];
        }
        if (isset($validated['notes'])) {
            $repair->notes = $validated['notes'];
        }
        $repair->save();

        logActivity('Update Repair Status', 'repairs', $repair->id, "Updated repair #{$repair->id} status to {$repair->status}");

        return back()->with('success', "Repair status updated to {$repair->status}.");
    }
}
