@extends('layouts.app', ['title' => 'Low Stock Report'])

@section('content')
<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h3 class="card-title">Products Running Low or Out of Stock</h3>
    <a href="{{ route('inventory.bulk_stock') }}" class="btn btn-primary">+ Restock Products</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product Name</th>
            <th>Category</th>
            <th>Current Stock</th>
            <th>Threshold</th>
            <th>Selling Price</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($products as $product)
          <tr>
            <td><code>{{ $product->sku }}</code></td>
            <td><strong>{{ $product->name }}</strong></td>
            <td><span class="badge badge-purple">{{ $product->category }}</span></td>
            <td>
              @if($product->current_stock == 0)
                <span class="badge badge-red">Out of Stock (0)</span>
              @else
                <span class="badge badge-yellow">{{ $product->current_stock }} remaining</span>
              @endif
            </td>
            <td>{{ $product->low_stock_threshold }}</td>
            <td>{{ money($product->selling_price) }}</td>
            <td style="text-align:right;">
              <a href="{{ route('inventory.bulk_stock') }}" class="btn btn-sm btn-secondary">Quick Restock</a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="text-center text-dim p-4">🎉 Excellent! No products are currently running low on stock.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">
      {{ $products->links() }}
    </div>
  </div>
</div>
@endsection
