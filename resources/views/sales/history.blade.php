@extends('layouts.app', ['title' => 'Sales History'])

@section('content')
<div class="stats-grid mb-4">
  <div class="stat-card accent-blue">
    <div class="stat-label">Total Selected Revenue</div>
    <div class="stat-value">{{ money($totalRevenue) }}</div>
  </div>
  <div class="stat-card accent-purple">
    <div class="stat-label">Total Cost of Goods</div>
    <div class="stat-value">{{ money($totalCost) }}</div>
  </div>
  <div class="stat-card accent-green">
    <div class="stat-label">Gross Profit</div>
    <div class="stat-value">{{ money($grossProfit) }}</div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('sales.history') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
      <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" style="max-width:160px;">
      <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" style="max-width:160px;">

      <select name="payment_method" class="form-control" style="max-width:150px;">
        <option value="">All Payments</option>
        <option value="Cash" {{ request('payment_method') == 'Cash' ? 'selected' : '' }}>Cash</option>
        <option value="Transfer" {{ request('payment_method') == 'Transfer' ? 'selected' : '' }}>Transfer</option>
        <option value="POS" {{ request('payment_method') == 'POS' ? 'selected' : '' }}>POS</option>
      </select>

      <input type="text" name="search" class="form-control" placeholder="Search customer or item..." value="{{ request('search') }}" style="max-width:200px;">

      <button type="submit" class="btn btn-secondary">Filter History</button>
      @if(request()->hasAny(['start_date', 'end_date', 'payment_method', 'search']))
        <a href="{{ route('sales.history') }}" class="btn btn-outline">Clear</a>
      @endif
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date / Time</th>
            <th>Customer</th>
            <th>Product Item</th>
            <th>Qty</th>
            <th>Selling Total</th>
            <th>Cost Price</th>
            <th>Profit</th>
            <th>Method</th>
            <th style="text-align:right;">Receipt</th>
          </tr>
        </thead>
        <tbody>
          @forelse($outflows as $outflow)
          @php $profit = $outflow->total_amount - ($outflow->cost_price * $outflow->quantity); @endphp
          <tr>
            <td>{{ $outflow->created_at->format('d M Y, h:i A') }}</td>
            <td><strong>{{ $outflow->customer_name }}</strong></td>
            <td>{{ $outflow->product->name ?? 'Product' }}</td>
            <td><span class="badge badge-blue">{{ $outflow->quantity }}</span></td>
            <td><strong>{{ money($outflow->total_amount) }}</strong></td>
            <td>{{ money($outflow->cost_price * $outflow->quantity) }}</td>
            <td><strong class="{{ $profit >= 0 ? 'text-green' : 'text-red' }}">{{ money($profit) }}</strong></td>
            <td><span class="badge badge-purple">{{ $outflow->payment_method }}</span></td>
            <td style="text-align:right;">
              @if($outflow->transaction_id)
                <a href="{{ route('sales.receipt', $outflow->transaction_id) }}" class="btn btn-sm btn-secondary" target="_blank">View Receipt</a>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="9" class="text-center text-dim p-4">No sales records found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">
      {{ $outflows->links() }}
    </div>
  </div>
</div>
@endsection
