@extends('layouts.app', ['title' => 'Customer Debt Ledger — ' . $customer->name])

@section('content')
<div class="grid-2-col" style="display:grid; grid-template-columns: 1.6fr 1fr; gap:24px;">
  <!-- Left Column: Transaction History & Payments -->
  <div>
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Stock Outflow / Purchases History</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Unpaid Debt</th>
              </tr>
            </thead>
            <tbody>
              @forelse($outflows as $o)
              @php $unpaid = $o->total_amount - $o->amount_paid; @endphp
              <tr>
                <td>{{ $o->created_at->format('d M Y') }}</td>
                <td><strong>{{ $o->product->name ?? 'Item' }}</strong></td>
                <td>{{ $o->quantity }}</td>
                <td>{{ money($o->total_amount) }}</td>
                <td>{{ money($o->amount_paid) }}</td>
                <td>
                  <strong class="{{ $unpaid > 0 ? 'text-red' : 'text-green' }}">{{ money($unpaid) }}</strong>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="6" class="text-center text-dim p-4">No purchases recorded for this customer.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Debt Payment History</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount Paid</th>
                <th>Method</th>
                <th>Logged By</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              @forelse($payments as $p)
              <tr>
                <td>{{ $p->created_at->format('d M Y, h:i A') }}</td>
                <td><strong class="text-green">{{ money($p->amount) }}</strong></td>
                <td><span class="badge badge-purple">{{ $p->payment_method }}</span></td>
                <td>{{ $p->user->full_name ?? 'System' }}</td>
                <td>{{ $p->notes ?? 'N/A' }}</td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center text-dim p-4">No debt payment receipts recorded yet.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Balance Summary Card & Record Payment Form -->
  <div>
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">Account Summary</h3>
      </div>
      <div class="card-body text-center">
        <h2 style="margin:0; font-size:1.1rem; color:var(--text-dim);">Current Outstanding Debt Balance</h2>
        @php $bal = $customer->balance; @endphp
        <div style="font-size:2.2rem; font-weight:800; margin:12px 0;" class="{{ $bal > 0 ? 'text-red' : 'text-green' }}">
          {{ money($bal) }}
        </div>
        <div style="font-size:0.875rem; color:var(--text-dim);">Customer Type: {{ $customer->type }} | Phone: {{ $customer->phone ?? 'N/A' }}</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Record Debt Payment</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('customers.payments.store', $customer->id) }}">
          @csrf
          <div class="form-group mb-3">
            <label class="form-label">Amount Paid (₦) *</label>
            <input type="number" step="0.01" name="amount" class="form-control form-control-lg" min="0.01" required placeholder="0.00">
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Payment Method *</label>
            <select name="payment_method" class="form-control" required>
              <option value="Cash">Cash</option>
              <option value="Transfer">Bank Transfer</option>
              <option value="POS">POS Terminal</option>
            </select>
          </div>

          <div class="form-group mb-4">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Receipt / transaction reference">
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-weight:700;">
            Record Payment →
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
