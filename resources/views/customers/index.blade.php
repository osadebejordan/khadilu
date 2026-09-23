@extends('layouts.app', ['title' => 'Customers & Debts Management'])

@section('content')
<div class="card mb-4">
  <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <form method="GET" action="{{ route('customers.index') }}" style="display:flex; gap:12px; flex:1;">
      <input type="text" name="search" class="form-control" placeholder="Search customer name or phone..." value="{{ request('search') }}" style="max-width:260px;">
      <button type="submit" class="btn btn-secondary">Search</button>
      @if(request('search'))
        <a href="{{ route('customers.index') }}" class="btn btn-outline">Clear</a>
      @endif
    </form>

    <button class="btn btn-primary" onclick="document.getElementById('addCustomerModal').style.display='flex'">
      + Add Customer Account
    </button>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Customer Name</th>
            <th>Phone</th>
            <th>Type</th>
            <th>Calculated Balance</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($customers as $customer)
          @php $balance = $customer->balance; @endphp
          <tr>
            <td><strong>{{ $customer->name }}</strong></td>
            <td>{{ $customer->phone ?? 'N/A' }}</td>
            <td><span class="badge badge-purple">{{ $customer->type }}</span></td>
            <td>
              @if($balance > 0)
                <span class="badge badge-red">Owes: {{ money($balance) }}</span>
              @elseif($balance < 0)
                <span class="badge badge-green">Credit: {{ money(abs($balance)) }}</span>
              @else
                <span class="badge badge-outline">Clear (₦0.00)</span>
              @endif
            </td>
            <td style="text-align:right;">
              <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-sm btn-secondary">View Debt Ledger →</a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-dim p-4">No customers registered yet.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">
      {{ $customers->links() }}
    </div>
  </div>
</div>

<!-- Modal: Add Customer -->
<div id="addCustomerModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:500px; margin:20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Add Customer Account</h3>
      <button onclick="document.getElementById('addCustomerModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-dim);">✕</button>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('customers.store') }}">
        @csrf
        <div class="form-group mb-3">
          <label class="form-label">Customer Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Chukwuma OK">
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="08012345678">
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Customer Type *</label>
          <select name="type" class="form-control" required>
            <option value="Retail">Retail</option>
            <option value="Wholesale">Wholesale</option>
            <option value="Repair">Repair Client</option>
            <option value="Both">Both Retail & Repair</option>
          </select>
        </div>

        <div style="text-align:right;">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('addCustomerModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
