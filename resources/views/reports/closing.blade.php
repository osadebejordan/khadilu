@extends('layouts.app', ['title' => 'Daily Closing Report — ' . $targetDate->format('d M Y')])

@section('content')
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.closing') }}" style="display:flex; gap:12px; align-items:center;">
      <label class="form-label" style="margin:0;">Select Report Date:</label>
      <input type="date" name="date" class="form-control" value="{{ $targetDate->toDateString() }}" style="max-width:180px;">
      <button type="submit" class="btn btn-secondary">Generate Report</button>
    </form>
  </div>
</div>

<div class="stats-grid mb-4">
  <div class="stat-card accent-blue">
    <div class="stat-label">Total Sales Revenue</div>
    <div class="stat-value">{{ money($totalSalesRevenue) }}</div>
    <div class="stat-sub">{{ $totalSalesCount }} transaction items</div>
  </div>

  <div class="stat-card accent-green">
    <div class="stat-label">Gross Sales Profit</div>
    <div class="stat-value">{{ money($grossSalesProfit) }}</div>
    <div class="stat-sub">Revenue minus Cost of Goods</div>
  </div>

  <div class="stat-card accent-purple">
    <div class="stat-label">Total Cash Collected</div>
    <div class="stat-value">{{ money($totalCashIn) }}</div>
    <div class="stat-sub">Cash sales + debt repayments</div>
  </div>

  <div class="stat-card accent-amber">
    <div class="stat-label">Net Daily Profit</div>
    <div class="stat-value">{{ money($netProfit) }}</div>
    <div class="stat-sub">Gross Profit minus Expenses ({{ money($totalExpenses) }})</div>
  </div>
</div>

<div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
  <!-- Payment Method Breakdown -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Sales Breakdown by Payment Method</h3>
    </div>
    <div class="card-body">
      <table class="table">
        <tbody>
          <tr>
            <td>💵 Cash Sales</td>
            <td><strong>{{ money($cashSales) }}</strong></td>
          </tr>
          <tr>
            <td>🏦 Bank Transfer</td>
            <td><strong>{{ money($transferSales) }}</strong></td>
          </tr>
          <tr>
            <td>💳 POS / Card Terminal</td>
            <td><strong>{{ money($posSales) }}</strong></td>
          </tr>
          <tr>
            <td>📝 Customer Debt Collected</td>
            <td><strong class="text-green">{{ money($totalDebtCollected) }}</strong></td>
          </tr>
          <tr>
            <td>🔧 Phone Repair Income</td>
            <td><strong>{{ money($totalRepairIncome) }}</strong></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Expenses Breakdown -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Daily Business Expenses</h3>
    </div>
    <div class="card-body p-0">
      <table class="table">
        <thead>
          <tr>
            <th>Category</th>
            <th>Description</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          @forelse($expenses as $ex)
          <tr>
            <td><span class="badge badge-purple">{{ $ex->category }}</span></td>
            <td>{{ $ex->description ?? 'N/A' }}</td>
            <td><strong class="text-red">{{ money($ex->amount) }}</strong></td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="text-center text-dim p-4">No expenses logged for this date.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
