<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receipt #{{ $transaction->id }} — Khadilu Phone Hub</title>
  <style>
    body {
      font-family: 'Courier New', Courier, monospace;
      max-width: 320px;
      margin: 20px auto;
      padding: 10px;
      background: #fff;
      color: #000;
    }
    .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
    .title { font-size: 1.2rem; font-weight: bold; }
    .sub { font-size: 0.8rem; }
    .items { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 0.85rem; }
    .items th, .items td { text-align: left; padding: 4px 0; }
    .items th:last-child, .items td:last-child { text-align: right; }
    .total-section { border-top: 1px dashed #000; padding-top: 8px; font-size: 0.9rem; }
    .row { display: flex; justify-content: space-between; margin-bottom: 4px; }
    .footer { text-align: center; margin-top: 16px; font-size: 0.75rem; border-top: 1px dashed #000; padding-top: 8px; }
    @media print {
      .no-print { display: none; }
      body { margin: 0; padding: 0; width: 100%; }
    }
  </style>
</head>
<body>
  <div class="no-print" style="margin-bottom:16px; display:flex; gap:10px;">
    <button onclick="window.print()" style="padding:8px 16px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer;">🖨 Print Receipt</button>
    <a href="{{ route('sales.pos') }}" style="padding:8px 16px; background:#6c757d; color:#fff; text-decoration:none; border-radius:4px;">New Sale →</a>
  </div>

  <div class="header">
    <div class="title">KHADILU PHONE HUB</div>
    <div class="sub">Sales Invoice / Official Receipt</div>
    <div class="sub">Ref: #INV-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</div>
    <div class="sub">Date: {{ $transaction->created_at->format('d/m/Y h:i A') }}</div>
  </div>

  <div style="font-size:0.8rem; margin-bottom:10px;">
    <div><strong>Customer:</strong> {{ $transaction->customer_name }}</div>
    <div><strong>Cashier:</strong> {{ $transaction->user->full_name ?? 'System' }}</div>
    <div><strong>Payment Method:</strong> {{ $transaction->payment_method }}</div>
  </div>

  <table class="items">
    <thead>
      <tr style="border-bottom: 1px solid #000;">
        <th>Item</th>
        <th>Qty</th>
        <th>Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($transaction->outflows as $outflow)
      <tr>
        <td>{{ $outflow->product->name ?? 'Product Item' }}</td>
        <td>{{ $outflow->quantity }}</td>
        <td>₦{{ number_format($outflow->total_amount, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="total-section">
    <div class="row">
      <span>Cart Total:</span>
      <strong>₦{{ number_format($transaction->total_cart_value, 2) }}</strong>
    </div>
    <div class="row">
      <span>Amount Paid:</span>
      <strong>₦{{ number_format($transaction->total_paid, 2) }}</strong>
    </div>
    @php $balance = $transaction->total_paid - $transaction->total_cart_value; @endphp
    <div class="row">
      <span>{{ $balance < 0 ? 'Debt Balance:' : 'Change:' }}</span>
      <strong>₦{{ number_format(abs($balance), 2) }}</strong>
    </div>
  </div>

  <div class="footer">
    Thank you for your business!<br>
    Please keep receipt for warranty claims.
  </div>
</body>
</html>
