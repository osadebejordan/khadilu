@extends('layouts.app', ['title' => 'Repair Ticket #' . $repair->id])

@section('content')
<div class="grid-2-col" style="display:grid; grid-template-columns:1.6fr 1fr; gap:24px;">
  <!-- Left Column: Repair Ticket Details -->
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Repair Job #REP-{{ str_pad($repair->id, 5, '0', STR_PAD_LEFT) }}</h3>
      <span class="badge badge-purple" style="font-size:0.9rem;">{{ $repair->status }}</span>
    </div>
    <div class="card-body">
      <div class="grid-2-col mb-4" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div>
          <div style="font-size:0.8rem; color:var(--text-dim);">Customer</div>
          <strong style="font-size:1.1rem;">{{ $repair->customer_name }}</strong>
          <div style="font-size:0.85rem; color:var(--text-dim);">Phone: {{ $repair->customer_phone ?? 'N/A' }}</div>
        </div>
        <div>
          <div style="font-size:0.8rem; color:var(--text-dim);">Device Model & Category</div>
          <strong style="font-size:1.1rem;">{{ $repair->device_model }}</strong>
          <div style="font-size:0.85rem; color:var(--text-dim);">Category: {{ $repair->category }}</div>
        </div>
      </div>

      <hr style="border-color:var(--border); margin:20px 0;">

      <div class="mb-4">
        <div style="font-size:0.8rem; color:var(--text-dim); margin-bottom:4px;">Reported Issue & Diagnostics</div>
        <div style="background:var(--card-bg-alt, rgba(255,255,255,0.03)); padding:14px; border-radius:8px; border:1px solid var(--border); font-size:0.95rem;">
          {{ $repair->issue_description }}
        </div>
      </div>

      <div class="grid-3-col" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
        <div>
          <div style="font-size:0.8rem; color:var(--text-dim);">Estimated Cost</div>
          <strong>{{ money($repair->estimated_cost) }}</strong>
        </div>
        <div>
          <div style="font-size:0.8rem; color:var(--text-dim);">Amount Paid</div>
          <strong class="text-green">{{ money($repair->amount_paid) }}</strong>
        </div>
        <div>
          <div style="font-size:0.8rem; color:var(--text-dim);">Outstanding Balance</div>
          @php $bal = $repair->estimated_cost - $repair->amount_paid; @endphp
          <strong class="{{ $bal > 0 ? 'text-red' : 'text-green' }}">{{ money($bal) }}</strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Status Update Form -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Update Status & Billing</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('repairs.status', $repair->id) }}">
        @csrf
        @method('PATCH')
        <div class="form-group mb-3">
          <label class="form-label">Repair Progress Status *</label>
          <select name="status" class="form-control" required>
            <option value="Received" {{ $repair->status == 'Received' ? 'selected' : '' }}>Received (Intake)</option>
            <option value="In-Progress" {{ $repair->status == 'In-Progress' ? 'selected' : '' }}>In-Progress (Working on device)</option>
            <option value="Ready" {{ $repair->status == 'Ready' ? 'selected' : '' }}>Ready for Customer Pickup</option>
            <option value="Delivered" {{ $repair->status == 'Delivered' ? 'selected' : '' }}>Delivered / Handed to Customer</option>
            <option value="Cancelled" {{ $repair->status == 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
          </select>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Total Amount Paid (₦)</label>
          <input type="number" step="0.01" name="amount_paid" class="form-control" value="{{ $repair->amount_paid }}" min="0">
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Technician Notes</label>
          <textarea name="notes" class="form-control" rows="3" placeholder="Parts replaced, warranty notes...">{{ $repair->notes }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-weight:700;">
          Save Status Update →
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
