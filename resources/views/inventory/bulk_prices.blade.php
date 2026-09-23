@extends('layouts.app', ['title' => 'Bulk Price Management'])

@section('content')
<div class="card mb-4">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h3 class="card-title">Bulk Product Price Editor</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('inventory.bulk_prices.update') }}">
      @csrf
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Product Name</th>
              <th>Category</th>
              <th>Current Cost (₦)</th>
              <th>Selling Price (₦)</th>
            </tr>
          </thead>
          <tbody>
            @foreach($products as $index => $product)
            <tr>
              <td><code>{{ $product->sku }}</code></td>
              <td><strong>{{ $product->name }}</strong></td>
              <td><span class="badge badge-purple">{{ $product->category }}</span></td>
              <td>
                <input type="hidden" name="prices[{{ $index }}][product_id]" value="{{ $product->id }}">
                <input type="number" step="0.01" name="prices[{{ $index }}][cost_price]" class="form-control" value="{{ $product->cost_price }}" style="max-width:140px;">
              </td>
              <td>
                <input type="number" step="0.01" name="prices[{{ $index }}][selling_price]" class="form-control" value="{{ $product->selling_price }}" required style="max-width:140px;">
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div style="text-align:right; margin-top:20px;">
        <button type="submit" class="btn btn-primary" style="padding:12px 28px; font-weight:700;">
          Save All Price Changes →
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
