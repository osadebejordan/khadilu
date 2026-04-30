</main><!-- /.main-content -->

<!-- Global Product View Modal -->
<div class="modal-overlay" id="global-view-modal" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal view-only">
        <div class="modal-header">
            <h3>Product Details</h3>
            <button class="modal-close" onclick="document.getElementById('global-view-modal').style.display='none'">✕</button>
        </div>
        <div class="view-grid">
            <div class="view-item" style="grid-column: span 2;"><label>Product Name</label><p id="v-name" class="bold accent-text"></p></div>
            <div class="view-item"><label>Brand</label><p id="v-brand"></p></div>
            <div class="view-item"><label>Category</label><p id="v-cat"></p></div>
            <div class="view-item"><label>Quality</label><p id="v-quality"></p></div>
            <div class="view-item"><label>SKU/IMEI</label><p id="v-sku" class="mono"></p></div>
            <div class="view-item"><label>Stock on Hand</label><p id="v-stock" class="bold"></p></div>
            <div class="view-item"><label>Selling Price</label><p id="v-price" class="accent-text mono"></p></div>
            <?php if(isAdmin()): ?>
                <div class="view-item"><label>Cost Price</label><p id="v-cost" class="dim mono"></p></div>
            <?php endif; ?>
            <div class="view-item" style="grid-column: span 2;"><label>Compatible Models</label><p id="v-compat" class="small"></p></div>
        </div>
        <div class="modal-actions" style="padding: 0 20px 20px;">
            <button type="button" class="btn btn-ghost btn-full" onclick="document.getElementById('global-view-modal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<script>
  const toggle = document.getElementById('mobileToggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  function closeSidebar() {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
  }
  toggle?.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
  });
  overlay?.addEventListener('click', closeSidebar);

  // Global Product Viewer
  window.viewProduct = function(id) {
    if(!id) return;
    $.getJSON('ajax_product_details.php', {id: id}, function(data) {
        if(data.error) return alert(data.error);
        
        $('#v-name').text(data.name);
        $('#v-brand').text(data.brand || 'N/A');
        $('#v-cat').text(data.category);
        $('#v-quality').text(data.quality);
        $('#v-sku').text(data.sku || 'N/A');
        $('#v-stock').text(data.current_stock + ' unit' + (data.current_stock === 1 ? '' : 's'));
        $('#v-price').text('₦' + parseFloat(data.selling_price).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#v-compat').text(data.compatible_models || 'None specified');
        
        <?php if(isAdmin()): ?>
        $('#v-cost').text('₦' + parseFloat(data.cost_price).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        <?php endif; ?>

        // Color code stock
        $('#v-stock').removeClass('red-text green-text yellow-text');
        if(data.current_stock <= 0) $('#v-stock').addClass('red-text');
        else if(data.current_stock <= data.low_stock_threshold) $('#v-stock').addClass('yellow-text');
        else $('#v-stock').addClass('green-text');

        $('#global-view-modal').css('display', 'flex');
    });
  };
</script>
</body>
</html>
