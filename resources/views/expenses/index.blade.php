@extends('layouts.app', ['title' => 'Business Expense Tracker'])

@section('content')
<div class="stats-grid mb-4">
  <div class="stat-card accent-amber">
    <div class="stat-label">Total Filtered Expenses</div>
    <div class="stat-value">{{ money($totalExpenses) }}</div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <form method="GET" action="{{ route('expenses.index') }}" style="display:flex; gap:12px; flex:1;">
      <select name="category" class="form-control" style="max-width:180px;">
        <option value="">All Categories</option>
        <option value="Rent" {{ request('category') == 'Rent' ? 'selected' : '' }}>Rent</option>
        <option value="Electricity" {{ request('category') == 'Electricity' ? 'selected' : '' }}>Electricity</option>
        <option value="Salary" {{ request('category') == 'Salary' ? 'selected' : '' }}>Salary</option>
        <option value="Marketing" {{ request('category') == 'Marketing' ? 'selected' : '' }}>Marketing</option>
        <option value="Transport" {{ request('category') == 'Transport' ? 'selected' : '' }}>Transport</option>
        <option value="Others" {{ request('category') == 'Others' ? 'selected' : '' }}>Others</option>
      </select>

      <button type="submit" class="btn btn-secondary">Filter</button>
      @if(request('category'))
        <a href="{{ route('expenses.index') }}" class="btn btn-outline">Clear</a>
      @endif
    </form>

    <button class="btn btn-primary" onclick="document.getElementById('expenseModal').style.display='flex'">
      + Log New Expense
    </button>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Expense Date</th>
            <th>Category</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Logged By</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($expenses as $e)
          <tr>
            <td>{{ date('d M Y', strtotime($e->expense_date)) }}</td>
            <td><span class="badge badge-purple">{{ $e->category }}</span></td>
            <td>{{ $e->description ?? 'N/A' }}</td>
            <td><strong class="text-red">{{ money($e->amount) }}</strong></td>
            <td>{{ $e->user->full_name ?? 'System' }}</td>
            <td style="text-align:right;">
              <form action="{{ route('expenses.destroy', $e->id) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this expense entry?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-dim p-4">No expense records found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">
      {{ $expenses->links() }}
    </div>
  </div>
</div>

<!-- Modal: Log Expense -->
<div id="expenseModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:500px; margin:20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Record Business Expense</h3>
      <button onclick="document.getElementById('expenseModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-dim);">✕</button>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('expenses.store') }}">
        @csrf
        <div class="form-group mb-3">
          <label class="form-label">Category *</label>
          <select name="category" class="form-control" required>
            <option value="Rent">Rent</option>
            <option value="Electricity">Electricity / Generator</option>
            <option value="Salary">Staff Salary</option>
            <option value="Marketing">Marketing / Ads</option>
            <option value="Transport">Transport / Logistics</option>
            <option value="Others" selected>Others</option>
          </select>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Amount (₦) *</label>
            <input type="number" step="0.01" name="amount" class="form-control" min="0.01" required placeholder="0.00">
          </div>
          <div>
            <label class="form-label">Expense Date *</label>
            <input type="date" name="expense_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Description / Remarks</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Details of expense"></textarea>
        </div>

        <div style="text-align:right;">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('expenseModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
