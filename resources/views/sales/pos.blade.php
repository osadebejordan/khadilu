@extends('layouts.app', ['title' => 'Point of Sale (POS)'])

@section('content')
<div class="grid-2-col" style="display:grid; grid-template-columns: 1.4fr 1fr; gap:24px;">
  <!-- Left Side: Product Search & Item List -->
  <div>
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title">🔍 Search Products to Add</h3>
      </div>
      <div class="card-body">
        <div style="position:relative;">
          <input type="text" id="posSearch" class="form-control form-control-lg" placeholder="Type product name, brand, or scan SKU barcode..." autocomplete="off" style="font-size:1.1rem; padding:14px;">
          <div id="searchResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; box-shadow:var(--shadow-lg); z-index:999; max-height:300px; overflow-y:auto; margin-top:4px;"></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">🛒 Cart Items</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table" id="cartTable">
            <thead>
              <tr>
                <th>Item</th>
                <th>Price (₦)</th>
                <th>Qty</th>
                <th>Subtotal</th>
                <th style="width:50px;"></th>
              </tr>
            </thead>
            <tbody id="cartBody">
              <tr id="emptyCartRow">
                <td colspan="5" class="text-center text-dim p-4">Cart is empty. Search products above to add items.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Side: Checkout Summary Panel -->
  <div>
    <div class="card" style="position:sticky; top:20px;">
      <div class="card-header">
        <h3 class="card-title">Checkout Summary</h3>
      </div>
      <div class="card-body">
        <form id="checkoutForm">
          <div class="form-group mb-3">
            <label class="form-label">Customer Type / Name *</label>
            <input type="text" id="customer_name" class="form-control" value="Walk-in Customer" required>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Link Customer (Optional for Debt Tracking)</label>
            <select id="customer_id" class="form-control">
              <option value="">-- No Linked Customer Account --</option>
              @foreach($customers as $c)
                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone }})</option>
              @endforeach
            </select>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Payment Method *</label>
            <select id="payment_method" class="form-control" required>
              <option value="Cash">Cash</option>
              <option value="Transfer">Bank Transfer</option>
              <option value="POS">POS / Card Terminal</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div style="background:var(--card-bg-alt, rgba(255,255,255,0.03)); padding:16px; border-radius:12px; margin:20px 0; border:1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:1.1rem;">
              <span>Total Cart Value:</span>
              <strong id="cartTotalDisplay" style="font-size:1.4rem; color:var(--text);">₦0.00</strong>
            </div>

            <div class="form-group mt-3">
              <label class="form-label">Amount Paid (₦) *</label>
              <input type="number" step="0.01" id="amount_paid" class="form-control form-control-lg" value="0.00" min="0" required style="font-weight:700;">
            </div>

            <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:0.9rem;">
              <span>Change / Debt Balance:</span>
              <strong id="balanceDisplay" class="text-green">₦0.00</strong>
            </div>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Sale Notes</label>
            <input type="text" id="notes" class="form-control" placeholder="Optional notes or warranty details">
          </div>

          <button type="submit" id="btnCheckout" class="btn btn-primary" style="width:100%; padding:14px; font-size:1.1rem; font-weight:800;" disabled>
            Complete Transaction →
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  let cart = [];

  document.addEventListener('DOMContentLoaded', () => {
    const posSearch = document.getElementById('posSearch');
    const searchResults = document.getElementById('searchResults');
    const checkoutForm = document.getElementById('checkoutForm');
    const customerSelect = document.getElementById('customer_id');

    customerSelect.addEventListener('change', (e) => {
      const selectedOption = e.target.options[e.target.selectedIndex];
      if (e.target.value) {
        document.getElementById('customer_name').value = selectedOption.text.split('(')[0].trim();
      }
    });

    posSearch.addEventListener('input', async (e) => {
      const q = e.target.value.trim();
      if (q.length < 1) {
        searchResults.style.display = 'none';
        return;
      }

      try {
        const res = await fetch(`/sales/search-products?q=${encodeURIComponent(q)}`);
        const products = await res.json();

        if (products.length === 0) {
          searchResults.innerHTML = '<div style="padding:12px; color:var(--text-dim); text-align:center;">No matching products found.</div>';
        } else {
          searchResults.innerHTML = products.map(p => `
            <div class="search-item" onclick='addToCart(${JSON.stringify(p)})' style="padding:12px; border-bottom:1px solid var(--border); cursor:pointer; display:flex; justify-space-between; align-items:center;">
              <div>
                <strong>${p.name}</strong>
                <div style="font-size:0.75rem; color:var(--text-dim);">SKU: ${p.sku} | Grade: ${p.quality}</div>
              </div>
              <div style="text-align:right;">
                <div style="font-weight:700; color:var(--text);">${p.formatted_price}</div>
                <div style="font-size:0.75rem; color:var(--text-dim);">${p.current_stock} in stock</div>
              </div>
            </div>
          `).join('');
        }
        searchResults.style.display = 'block';
      } catch (err) {
        console.error(err);
      }
    });

    document.addEventListener('click', (e) => {
      if (!posSearch.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
      }
    });

    document.getElementById('amount_paid').addEventListener('input', updateCartTotals);

    checkoutForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (cart.length === 0) return alert('Cart is empty.');

      const btn = document.getElementById('btnCheckout');
      btn.disabled = true;
      btn.innerText = 'Processing Sale...';

      const payload = {
        customer_name: document.getElementById('customer_name').value.trim(),
        customer_id: document.getElementById('customer_id').value || null,
        payment_method: document.getElementById('payment_method').value,
        amount_paid: parseFloat(document.getElementById('amount_paid').value || 0),
        notes: document.getElementById('notes').value.trim(),
        cart: cart.map(item => ({
          product_id: item.id,
          quantity: item.qty,
          price: item.selling_price
        }))
      };

      try {
        const res = await fetch('{{ route("sales.checkout") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
          window.location.href = data.redirect_url;
        } else {
          alert(data.message || 'Error processing transaction.');
          btn.disabled = false;
          btn.innerText = 'Complete Transaction →';
        }
      } catch (err) {
        alert('Server error processing payment.');
        btn.disabled = false;
        btn.innerText = 'Complete Transaction →';
      }
    });
  });

  function addToCart(product) {
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('posSearch').value = '';

    const existing = cart.find(item => item.id === product.id);
    if (existing) {
      if (existing.qty < product.current_stock) {
        existing.qty++;
      } else {
        alert('Cannot exceed available stock limit (' + product.current_stock + ').');
      }
    } else {
      cart.push({ ...product, qty: 1 });
    }
    renderCart();
  }

  function updateQty(index, newQty) {
    const item = cart[index];
    newQty = parseInt(newQty);
    if (isNaN(newQty) || newQty < 1) newQty = 1;
    if (newQty > item.current_stock) {
      alert('Cannot exceed available stock limit (' + item.current_stock + ').');
      newQty = item.current_stock;
    }
    item.qty = newQty;
    renderCart();
  }

  function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
  }

  function renderCart() {
    const tbody = document.getElementById('cartBody');
    if (cart.length === 0) {
      tbody.innerHTML = '<tr id="emptyCartRow"><td colspan="5" class="text-center text-dim p-4">Cart is empty. Search products above to add items.</td></tr>';
      document.getElementById('btnCheckout').disabled = true;
    } else {
      tbody.innerHTML = cart.map((item, index) => `
        <tr>
          <td>
            <strong>${item.name}</strong>
            <div style="font-size:0.75rem; color:var(--text-dim);">${item.sku}</div>
          </td>
          <td>₦${item.selling_price.toLocaleString()}</td>
          <td>
            <input type="number" class="form-control" style="width:70px;" value="${item.qty}" min="1" max="${item.current_stock}" onchange="updateQty(${index}, this.value)">
          </td>
          <td><strong>₦${(item.selling_price * item.qty).toLocaleString()}</strong></td>
          <td>
            <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">✕</button>
          </td>
        </tr>
      `).join('');
      document.getElementById('btnCheckout').disabled = false;
    }

    updateCartTotals();
  }

  function updateCartTotals() {
    const total = cart.reduce((sum, item) => sum + (item.selling_price * item.qty), 0);
    document.getElementById('cartTotalDisplay').innerText = '₦' + total.toLocaleString('en-US', { minimumFractionDigits: 2 });
    
    const paidInput = document.getElementById('amount_paid');
    if (!paidInput.dataset.manual) {
      paidInput.value = total.toFixed(2);
    }

    const paid = parseFloat(paidInput.value || 0);
    const diff = paid - total;
    const balanceDisplay = document.getElementById('balanceDisplay');

    if (diff < 0) {
      balanceDisplay.className = 'text-red';
      balanceDisplay.innerText = 'Debt Balance: ₦' + Math.abs(diff).toLocaleString('en-US', { minimumFractionDigits: 2 });
    } else {
      balanceDisplay.className = 'text-green';
      balanceDisplay.innerText = 'Change: ₦' + diff.toLocaleString('en-US', { minimumFractionDigits: 2 });
    }
  }

  document.getElementById('amount_paid').addEventListener('focus', function() {
    this.dataset.manual = "true";
  });
</script>
@endsection
