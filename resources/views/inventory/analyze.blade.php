@extends('layouts.app', ['title' => 'Stock Valuation Analytics'])

@section('content')
<div class="stats-grid mb-4">
  <div class="stat-card accent-blue">
    <div class="stat-label">Total Cost Valuation</div>
    <div class="stat-value">{{ money($totalCostValue) }}</div>
    <div class="stat-sub">Sum of (Cost Price × Stock)</div>
  </div>

  <div class="stat-card accent-purple">
    <div class="stat-label">Total Retail Valuation</div>
    <div class="stat-value">{{ money($totalSellingValue) }}</div>
    <div class="stat-sub">Sum of (Selling Price × Stock)</div>
  </div>

  <div class="stat-card accent-green">
    <div class="stat-label">Potential Gross Margin</div>
    <div class="stat-value">{{ money($potentialProfit) }}</div>
    <div class="stat-sub">Retail Value minus Cost Value</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Inventory Valuation by Category</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Category Name</th>
            <th>Total Units in Stock</th>
            <th>Estimated Retail Value</th>
          </tr>
        </thead>
        <tbody>
          @foreach($categoryStats as $stat)
          <tr>
            <td><strong>{{ $stat->category }}</strong></td>
            <td><span class="badge badge-blue">{{ number_format($stat->total_qty) }} units</span></td>
            <td><strong>{{ money($stat->category_value) }}</strong></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
