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
  // Wrap in an IIFE to prevent variable naming conflicts on specific pages like add_stock.php
  (function() {
    const mToggle = document.getElementById('mobileToggle');
    const mSidebar = document.querySelector('.sidebar');
    const mOverlay = document.getElementById('sidebarOverlay');

    const closeSidebar = () => {
      mSidebar?.classList.remove('active');
      mOverlay?.classList.remove('active');
    };

    mToggle?.addEventListener('click', (e) => {
      e.preventDefault();
      mSidebar?.classList.toggle('active');
      mOverlay?.classList.toggle('active');
    });

    mOverlay?.addEventListener('click', closeSidebar);
  })();

  // Global fix for number inputs: Auto-select text on focus to allow instant overwriting
  $(document).on('focus', 'input[type="number"]', function() {
      $(this).select();
  });

  // UX: Prevent double-submission and show loading state
  $(document).on('submit', 'form', function() {
      const $btn = $(this).find('button[type="submit"]');
      if ($btn.length && !$btn.hasClass('no-loader')) {
          $btn.addClass('loading');
      }
  });

  // UX: Global Keyboard Shortcuts
  $(document).on('keydown', function(e) {
      if (e.key === "/" && !$(e.target).is('input, textarea, select')) {
          e.preventDefault();
          $('input[name="q"], .select2-search__field').first().focus();
      }
  });

  // Global Product Viewer
  window.viewProduct = function(id) {
    if(!id) return;
    $.getJSON('ajax_product_details.php', {id: id}, function(data) {
        if(data.error) return alert(data.error);
        
        $('#v-name').html(data.name + ' <span class="bold">[' + data.quality + ']</span>');
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
