@extends('layouts.app', ['title' => 'Bulk Stock Restock'])

@section('content')
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Stock Inflow / Restock Entry</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('inventory.bulk_stock.store') }}">
      @csrf
      <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;">
        <div>
          <label class="form-label">Supplier Name</label>
          <input type="text" name="supplier" class="form-control" placeholder="e.g. Lagos Wholesale Electronics Market">
        </div>
        <div>
          <label class="form-label">Notes / Reference</label>
          <input type="text" name="notes" class="form-control" placeholder="Invoice # or delivery note reference">
        </div>
      </div>

      <div class="table-responsive">
        <table class="table" id="bulkStockTable">
          <thead>
            <tr>
              <th style="width:50%;">Select Product</th>
              <th style="width:20%;">Quantity to Add</th>
              <th style="width:20%;">Cost per Unit (₦)</th>
              <th style="width:10%;">Action</th>
            </tr>
          </thead>
          <tbody id="bulkRows">
            <tr class="stock-row">
              <td>
                <select name="items[0][product_id]" class="form-control product-select" required>
                  <option value="">-- Choose Product --</option>
                  @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }} (Current: {{ $product->current_stock }} | SKU: {{ $product->sku }})</option>
                  @endforeach
                </select>
              </td>
              <td>
                <input type="number" name="items[0][quantity]" class="form-control" min="1" required placeholder="0">
              </td>
              <td>
                <input type="number" step="0.01" name="items[0][cost_per_unit]" class="form-control" min="0" required placeholder="0.00">
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-danger remove-row" onclick="removeRow(this)">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
        <button type="button" class="btn btn-secondary" onclick="addRow()">+ Add Another Product Row</button>
        <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Submit Stock Entry →</button>
      </div>
    </form>
  </div>
</div>

<script>
  let rowIndex = 1;
  const productOptions = `@foreach($products as $product)<option value="{{ $product->id }}">{{ addslashes($product->name) }} (Current: {{ $product->current_stock }} | SKU: {{ $product->sku }})</option>@endforeach`;

  function addRow() {
    const tbody = document.getElementById('bulkRows');
    const tr = document.createElement('tr');
    tr.className = 'stock-row';
    tr.innerHTML = `
      <td>
        <select name="items[${rowIndex}][product_id]" class="form-control product-select" required>
          <option value="">-- Choose Product --</option>
          ${productOptions}
        </select>
      </td>
      <td>
        <input type="number" name="items[${rowIndex}][quantity]" class="form-control" min="1" required placeholder="0">
      </td>
      <td>
        <input type="number" step="0.01" name="items[${rowIndex}][cost_per_unit]" class="form-control" min="0" required placeholder="0.00">
      </td>
      <td>
        <button type="button" class="btn btn-sm btn-danger remove-row" onclick="removeRow(this)">Remove</button>
      </td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
  }

  function removeRow(btn) {
    const rows = document.querySelectorAll('#bulkRows tr');
    if (rows.length > 1) {
      btn.closest('tr').remove();
    } else {
      alert('You must submit at least one product row.');
    }
  }
</script>
@endsection
