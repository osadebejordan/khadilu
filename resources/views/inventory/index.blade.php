@extends('layouts.app', ['title' => 'Inventory Management'])

@section('content')
<div class="card mb-4">
  <div class="card-body">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
      <form method="GET" action="{{ route('inventory.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; flex:1;">
        <input type="text" name="search" class="form-control" placeholder="Search product name, SKU, brand..." value="{{ request('search') }}" style="max-width: 250px;">
        
        <select name="category" class="form-control" style="max-width: 160px;">
          <option value="">All Categories</option>
          @foreach($categories as $cat)
            <option value="{{ $cat->name }}" {{ request('category') == $cat->name ? 'selected' : '' }}>{{ $cat->name }}</option>
          @endforeach
        </select>

        <select name="quality" class="form-control" style="max-width: 150px;">
          <option value="">All Qualities</option>
          @foreach($qualities as $q)
            <option value="{{ $q->name }}" {{ request('quality') == $q->name ? 'selected' : '' }}>{{ $q->name }}</option>
          @endforeach
        </select>

        <select name="stock_status" class="form-control" style="max-width: 150px;">
          <option value="">Stock Status</option>
          <option value="in" {{ request('stock_status') == 'in' ? 'selected' : '' }}>In Stock</option>
          <option value="low" {{ request('stock_status') == 'low' ? 'selected' : '' }}>Low Stock</option>
          <option value="out" {{ request('stock_status') == 'out' ? 'selected' : '' }}>Out of Stock</option>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>
        @if(request()->hasAny(['search', 'category', 'quality', 'stock_status']))
          <a href="{{ route('inventory.index') }}" class="btn btn-outline" style="text-decoration:none;">Clear</a>
        @endif
      </form>

      <button class="btn btn-primary" onclick="document.getElementById('addProductModal').style.display='flex'">
        + Add New Product
      </button>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product Name</th>
            <th>Category / Quality</th>
            <th>Stock</th>
            <th>Cost Price</th>
            <th>Selling Price</th>
            <th>Margin</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($products as $product)
          <tr>
            <td><code style="font-family:'JetBrains Mono', monospace; font-size:0.8rem;">{{ $product->sku }}</code></td>
            <td>
              <strong>{{ $product->name }}</strong>
              @if($product->brand)
                <div style="font-size:0.75rem; color:var(--text-dim);">Brand: {{ $product->brand }}</div>
              @endif
            </td>
            <td>
              <span class="badge badge-purple" style="font-size:0.75rem;">{{ $product->category }}</span>
              <span class="badge badge-outline" style="font-size:0.75rem;">{{ $product->quality }}</span>
            </td>
            <td>
              @if($product->current_stock == 0)
                <span class="badge badge-red">Out of Stock</span>
              @elseif($product->is_low_stock)
                <span class="badge badge-yellow">{{ $product->current_stock }} (Low)</span>
              @else
                <span class="badge badge-green">{{ $product->current_stock }}</span>
              @endif
            </td>
            <td>{{ money($product->cost_price) }}</td>
            <td><strong>{{ money($product->selling_price) }}</strong></td>
            <td>
              <span class="text-green" style="font-weight:700;">{{ $product->margin_percentage }}%</span>
              <div style="font-size:0.75rem; color:var(--text-dim);">({{ money($product->margin_amount) }})</div>
            </td>
            <td style="text-align:right;">
              <button class="btn btn-sm btn-secondary" onclick='openEditModal({{ json_encode($product) }})'>Edit</button>
              <form action="{{ route('inventory.destroy', $product->id) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this product from inventory?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center text-dim p-4">No products found matching criteria.</td>
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

<!-- Modal: Add Product -->
<div id="addProductModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:600px; margin:20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Add New Product</h3>
      <button onclick="document.getElementById('addProductModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-dim);">✕</button>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('inventory.store') }}">
        @csrf
        <div class="form-group mb-3">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. iPhone 13 Original Screen Replacement">
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Brand</label>
            <input type="text" name="brand" class="form-control" placeholder="e.g. Apple, Samsung">
          </div>
          <div>
            <label class="form-label">SKU (Auto-generated if empty)</label>
            <input type="text" name="sku" class="form-control" placeholder="Leave blank to auto-generate">
          </div>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Category *</label>
            <select name="category" class="form-control" required>
              @foreach($categories as $cat)
                <option value="{{ $cat->name }}">{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Quality Grade *</label>
            <select name="quality" class="form-control" required>
              @foreach($qualities as $q)
                <option value="{{ $q->name }}">{{ $q->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="grid-3-col" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Initial Stock *</label>
            <input type="number" name="current_stock" class="form-control" value="0" min="0" required>
          </div>
          <div>
            <label class="form-label">Cost Price (₦) *</label>
            <input type="number" step="0.01" name="cost_price" class="form-control" value="0.00" min="0" required>
          </div>
          <div>
            <label class="form-label">Selling Price (₦) *</label>
            <input type="number" step="0.01" name="selling_price" class="form-control" value="0.00" min="0" required>
          </div>
        </div>

        <div class="form-group mb-4">
          <label class="form-label">Low Stock Threshold</label>
          <input type="number" name="low_stock_threshold" class="form-control" value="3" min="0" required>
        </div>

        <div style="text-align:right;">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('addProductModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit Product -->
<div id="editProductModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:600px; margin:20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title">Edit Product</h3>
      <button onclick="document.getElementById('editProductModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-dim);">✕</button>
    </div>
    <div class="card-body">
      <form id="editProductForm" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group mb-3">
          <label class="form-label">Product Name *</label>
          <input type="text" id="edit_name" name="name" class="form-control" required>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Brand</label>
            <input type="text" id="edit_brand" name="brand" class="form-control">
          </div>
          <div>
            <label class="form-label">Category *</label>
            <select id="edit_category" name="category" class="form-control" required>
              @foreach($categories as $cat)
                <option value="{{ $cat->name }}">{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Quality Grade *</label>
            <select id="edit_quality" name="quality" class="form-control" required>
              @foreach($qualities as $q)
                <option value="{{ $q->name }}">{{ $q->name }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Low Stock Threshold</label>
            <input type="number" id="edit_low_stock_threshold" name="low_stock_threshold" class="form-control" required>
          </div>
        </div>

        <div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
          <div>
            <label class="form-label">Cost Price (₦) *</label>
            <input type="number" step="0.01" id="edit_cost_price" name="cost_price" class="form-control" required>
          </div>
          <div>
            <label class="form-label">Selling Price (₦) *</label>
            <input type="number" step="0.01" id="edit_selling_price" name="selling_price" class="form-control" required>
          </div>
        </div>

        <div style="text-align:right;">
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('editProductModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function openEditModal(product) {
    document.getElementById('editProductForm').action = '/inventory/' + product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_brand').value = product.brand || '';
    document.getElementById('edit_category').value = product.category;
    document.getElementById('edit_quality').value = product.quality;
    document.getElementById('edit_cost_price').value = product.cost_price;
    document.getElementById('edit_selling_price').value = product.selling_price;
    document.getElementById('edit_low_stock_threshold').value = product.low_stock_threshold;
    document.getElementById('editProductModal').style.display = 'flex';
  }
</script>
@endsection
