@extends('layouts.app', ['title' => 'Stock Returns Processing'])

@section('content')
<div class="card mb-4">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h3 class="card-title">Customer Stock Returns Request</h3>
    <button class="btn btn-primary" onclick="document.getElementById('returnModal').style.display='flex'">
      + Log Customer Return
    </button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Product Returned</th>
            <th>Customer</th>
            <th>Qty</th>
            <th>Refund Amount</th>
            <th>Status</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($returns as $r)
          <tr>
            <td>{{ $r->created_at->format('d M Y, h:i A') }}</td>
            <td><strong>{{ $r->product->name ?? 'N/A' }}</strong></td>
            <td>{{ $r->customer->name ?? ($r->customer_name ?? 'Walk-in') }}</td>
            <td><span class="badge badge-blue">{{ $r->quantity }}</span></td>
            <td><strong>{{ money($r->refund_amount) }}</strong></td>
            <td>
              @if($r->status === 'Approved')
                <span class="badge badge-green">Approved (Restocked)</span>
              @elseif($r->status === 'Rejected')
                <span class="badge badge-red">Rejected</span>
              @else
                <span class="badge badge-yellow">Pending Review</span>
              @endif
            </td>
            <td style="text-align:right;">
              @if($r->status === 'Pending')
                <form action="{{ route('returns.status', $r->id) }}" method="POST" style="display:inline-block;">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="status" value="Approved">
                  <button type="submit" class="btn btn-sm btn-success">Approve & Restock</button>
                </form>
                <form action="{{ route('returns.status', $r->id) }}" method="POST" style="display:inline-block;">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="status" value="Rejected">
                  <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                </form>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-dim p-4">No stock return requests logged.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">
      {{ $returns->links() }}
    </div>
  </div>
</div>

<!-- Modal: Log Return -->
<div id="returnModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:500px; margin:20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Record Stock Return</h3>
      <button onclick="document.getElementById('returnModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-dim);">✕</button>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('returns.store') }}">
        @csrf
        <div class="form-group mb-3">
          <label class="form-label">Product Item *</label>
          <select name="product_id" class="form-control" required>
            <option value="">-- Choose Product --</option>
            @foreach($products as $p)
              <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
            @endforeach
          </select>
        </div>

        <div class="form-group mb-3">
          <label class="form-label">Customer (Optional)</label>
          <select name="customer_id" class="form-control">
            <option value="">Walk-in / Unlinked Customer</option>
            @foreach($customers as $c)
              <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Quantity Returned *</label>
            <input type="number" name="quantity" class="form-control" value="1" min="1" required>
          </div>
          <div>
            <label class="form-label">Refund Amount (₦) *</label>
            <input type="number" step="0.01" name="refund_amount" class="form-control" value="0.00" min="0" required>
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Reason / Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Defective screen or wrong model purchased"></textarea>
        </div>

        <div style="text-align:right;">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('returnModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Return Request</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
