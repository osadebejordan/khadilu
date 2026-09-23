@extends('layouts.app', ['title' => 'Dashboard Overview'])

@section('content')
<div class="stats-grid">
  <div class="stat-card accent-blue">
    <div class="stat-label">Today's Revenue</div>
    <div class="stat-value">{{ shortMoney($todayRevenue) }}</div>
    <div class="stat-sub">Sales recorded today</div>
  </div>

  <div class="stat-card accent-green">
    <div class="stat-label">Monthly Revenue</div>
    <div class="stat-value">{{ shortMoney($monthRevenue) }}</div>
    <div class="stat-sub">Total sales this month</div>
  </div>

  <div class="stat-card accent-purple">
    <div class="stat-label">Inventory Asset Value</div>
    <div class="stat-value">{{ shortMoney($totalSellingValue) }}</div>
    <div class="stat-sub">Cost: {{ shortMoney($totalCostValue) }}</div>
  </div>

  <div class="stat-card accent-amber">
    <div class="stat-label">Estimated Month Profit</div>
    <div class="stat-value">{{ shortMoney($estimatedMonthProfit) }}</div>
    <div class="stat-sub">Revenue - COGS - Expenses</div>
  </div>
</div>

<div class="grid-2-col" style="margin-top: 24px; display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
  <!-- Recent Sales -->
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Recent Transactions</h3>
      <a href="{{ route('sales.history') }}" class="btn btn-sm btn-secondary">View All History →</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Product</th>
              <th>Qty</th>
              <th>Amount</th>
              <th>Customer</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentSales as $sale)
            <tr>
              <td><span class="text-dim" style="font-size:0.8rem;">{{ $sale->created_at->diffForHumans() }}</span></td>
              <td><strong>{{ $sale->product->name ?? 'N/A' }}</strong></td>
              <td><span class="badge badge-blue">{{ $sale->quantity }}</span></td>
              <td><strong class="text-green">{{ money($sale->total_amount) }}</strong></td>
              <td>{{ $sale->customer_name }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="text-center text-dim p-4">No recent sales transactions recorded.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Low Stock Alert Box -->
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Low Stock Alerts</h3>
      <span class="badge badge-yellow">{{ $lowStockProducts->count() }} items</span>
    </div>
    <div class="card-body p-0">
      <ul class="list-group" style="list-style:none; padding:0; margin:0;">
        @forelse($lowStockProducts as $prod)
        <li style="padding: 12px 16px; border-bottom: 1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
          <div>
            <div style="font-weight:700; font-size:0.9rem;">{{ $prod->name }}</div>
            <div style="font-size:0.75rem; color:var(--text-dim);">Category: {{ $prod->category }} | SKU: {{ $prod->sku }}</div>
          </div>
          <div style="text-align:right;">
            <span class="badge {{ $prod->current_stock == 0 ? 'badge-red' : 'badge-yellow' }}">
              {{ $prod->current_stock }} left
            </span>
          </div>
        </li>
        @empty
        <li class="p-4 text-center text-dim" style="font-size:0.875rem;">
          🎉 All inventory items are well-stocked!
        </li>
        @endforelse
      </ul>
    </div>
  </div>
</div>
@endsection
