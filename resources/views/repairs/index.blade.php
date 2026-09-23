@extends('layouts.app', ['title' => 'Phone Repairs Management'])

@section('content')
<div class="card mb-4">
  <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <form method="GET" action="{{ route('repairs.index') }}" style="display:flex; gap:12px; flex:1;">
      <input type="text" name="search" class="form-control" placeholder="Search customer, device model, issue..." value="{{ request('search') }}" style="max-width:260px;">
      
      <select name="status" class="form-control" style="max-width:160px;">
        <option value="">All Statuses</option>
        <option value="Received" {{ request('status') == 'Received' ? 'selected' : '' }}>Received</option>
        <option value="In-Progress" {{ request('status') == 'In-Progress' ? 'selected' : '' }}>In-Progress</option>
        <option value="Ready" {{ request('status') == 'Ready' ? 'selected' : '' }}>Ready for Pickup</option>
        <option value="Delivered" {{ request('status') == 'Delivered' ? 'selected' : '' }}>Delivered</option>
        <option value="Cancelled" {{ request('status') == 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
      </select>

      <button type="submit" class="btn btn-secondary">Filter</button>
      @if(request()->hasAny(['search', 'status']))
        <a href="{{ route('repairs.index') }}" class="btn btn-outline">Clear</a>
      @endif
    </form>

    <button class="btn btn-primary" onclick="document.getElementById('newRepairModal').style.display='flex'">
      + Log New Repair Ticket
    </button>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Ticket #</th>
            <th>Customer Name</th>
            <th>Device Model</th>
            <th>Issue Description</th>
            <th>Estimated Cost</th>
            <th>Amount Paid</th>
            <th>Status</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($repairs as $repair)
          <tr>
            <td><code>#REP-{{ str_pad($repair->id, 5, '0', STR_PAD_LEFT) }}</code></td>
            <td>
              <strong>{{ $repair->customer_name }}</strong>
              <div style="font-size:0.75rem; color:var(--text-dim);">{{ $repair->customer_phone ?? 'No phone' }}</div>
            </td>
            <td><strong>{{ $repair->device_model }}</strong></td>
            <td><span style="font-size:0.875rem;">{{ Str::limit($repair->issue_description, 40) }}</span></td>
            <td>{{ money($repair->estimated_cost) }}</td>
            <td><strong class="text-green">{{ money($repair->amount_paid) }}</strong></td>
            <td>
              @php
                $statusBadges = [
                  'Received' => 'badge-purple',
                  'In-Progress' => 'badge-yellow',
                  'Ready' => 'badge-blue',
                  'Delivered' => 'badge-green',
                  'Cancelled' => 'badge-red',
                ];
              @endphp
              <span class="badge {{ $statusBadges[$repair->status] ?? 'badge-outline' }}">
                {{ $repair->status }}
              </span>
            </td>
            <td style="text-align:right;">
              <a href="{{ route('repairs.show', $repair->id) }}" class="btn btn-sm btn-secondary">Manage Ticket →</a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center text-dim p-4">No repair jobs found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">
      {{ $repairs->links() }}
    </div>
  </div>
</div>

<!-- Modal: Log Repair -->
<div id="newRepairModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:600px; margin:20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Log New Device Repair</h3>
      <button onclick="document.getElementById('newRepairModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-dim);">✕</button>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('repairs.store') }}">
        @csrf
        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Customer Name *</label>
            <input type="text" name="customer_name" class="form-control" required placeholder="Full Name">
          </div>
          <div>
            <label class="form-label">Customer Phone</label>
            <input type="text" name="customer_phone" class="form-control" placeholder="08012345678">
          </div>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Device Brand & Model *</label>
            <input type="text" name="device_model" class="form-control" required placeholder="e.g. Samsung Galaxy S21">
          </div>
          <div>
            <label class="form-label">Category *</label>
            <input type="text" name="category" class="form-control" value="Screen Replacement" required>
          </div>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Issue Description *</label>
          <textarea name="issue_description" class="form-control" rows="3" required placeholder="Describe the fault, crack, battery issue, or diagnostic details..."></textarea>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Estimated Repair Cost (₦)</label>
            <input type="number" step="0.01" name="estimated_cost" class="form-control" value="0.00" min="0">
          </div>
          <div>
            <label class="form-label">Advance Deposit Paid (₦)</label>
            <input type="number" step="0.01" name="amount_paid" class="form-control" value="0.00" min="0">
          </div>
        </div>

        <div style="text-align:right;">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('newRepairModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Repair Ticket</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
