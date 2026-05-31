<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$page_title = 'Stock Entry';
$msg = '';
$msg_type = '';

// Handle Stock Inflow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.';
        $msg_type = 'error';
    } else {
        try {
            $conn->begin_transaction();
            $uid = $_SESSION['khadilu_user_id'];
            $processed_count = 0;

            $inflow_stmt = $conn->prepare("INSERT INTO stock_inflow (product_id, user_id, quantity, cost_per_unit, total_cost, supplier, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $prod_stmt = $conn->prepare("UPDATE products SET current_stock = current_stock + ?, cost_price = ?, selling_price = ?, updated_at = NOW() WHERE id = ?");

            // 1. Process Existing Products
            if (isset($_POST['qty']) && is_array($_POST['qty'])) {
                foreach ($_POST['qty'] as $id => $q) {
                    $qty = intval($q);
                    if ($qty <= 0) continue;

                    $id = intval($id);
                    $cpu = floatval($_POST['cpu'][$id]);
                    $sell = floatval($_POST['sell'][$id]);
                    $total = $qty * $cpu;
                    $sup = trim($_POST['supplier'][$id]) ?: 'Unknown';
                    $note = trim($_POST['notes'][$id]);

                    $inflow_stmt->bind_param("iiiddss", $id, $uid, $qty, $cpu, $total, $sup, $note);
                    $inflow_stmt->execute();

                    $prod_stmt->bind_param("iddi", $qty, $cpu, $sell, $id);
                    $prod_stmt->execute();
                    
                    $processed_count++;
                }
            }

            // 2. Process Brand New Products
            if (isset($_POST['new_name']) && is_array($_POST['new_name'])) {
                $new_prod_stmt = $conn->prepare("INSERT INTO products (name, brand, category, quality, cost_price, selling_price, current_stock, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($_POST['new_name'] as $k => $name) {
                    $name = trim($name);
                    if (empty($name)) continue;

                    $brand = trim($_POST['new_brand'][$k]);
                    $cat   = trim($_POST['new_cat'][$k]);
                    $qual  = trim($_POST['new_qual'][$k]);
                    $qty   = intval($_POST['new_qty'][$k]);
                    $cpu   = floatval($_POST['new_cpu'][$k]);
                    $sell  = floatval($_POST['new_sell'][$k]);
                    $sku   = generateSKU();

                    $new_prod_stmt->bind_param("ssssddis", $name, $brand, $cat, $qual, $cpu, $sell, $qty, $sku);
                    $new_prod_stmt->execute();
                    $new_id = $conn->insert_id;

                    $sup   = trim($_POST['new_supplier'][$k]) ?: 'Unknown';
                    $note  = trim($_POST['new_notes'][$k]);
                    $total = $qty * $cpu;
                    $inflow_stmt->bind_param("iiiddss", $new_id, $uid, $qty, $cpu, $total, $sup, $note);
                    $inflow_stmt->execute();
                    $processed_count++;
                }
            }

            if ($processed_count > 0) {
                $conn->commit();
                logActivity('STOCK_INFLOW', 'products', null, "Added stock for $processed_count items.");
                $msg = "✓ Success! $processed_count items added to inventory.";
                $msg_type = 'success';
            } else {
                $conn->rollback();
                $msg = "No quantities were entered.";
                $msg_type = 'error';
            }
        } catch (Exception $e) {
            $conn->rollback();
            log_error($e, 'STOCK_ENTRY');
            $msg = "Error: " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// Filtering Logic
$prod_id = intval($_GET['product_id'] ?? 0);
$brand_f = $_GET['brand'] ?? '';
$cat_f = $_GET['category'] ?? '';
$qual_f = $_GET['quality'] ?? '';

// If we have a product_id (Restock), auto-set filters to match that product
if ($prod_id > 0 && !$brand_f && !$cat_f) {
    $stmt = $conn->prepare("SELECT brand, category, quality FROM products WHERE id = ?");
    $stmt->bind_param("i", $prod_id);
    $stmt->execute();
    $p_meta = $stmt->get_result()->fetch_assoc();
    if ($p_meta) {
        $brand_f = $p_meta['brand'];
        $cat_f = $p_meta['category'];
        $qual_f = $p_meta['quality'];
    }
}

// Fetch all active products for client-side filtering
$sql = "SELECT id, name, brand, category, quality, cost_price, selling_price, current_stock FROM products WHERE is_deleted = 0 ORDER BY brand ASC, name ASC";
$products = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$brands = $conn->query("SELECT DISTINCT brand FROM products WHERE is_deleted=0 AND brand IS NOT NULL ORDER BY brand")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$qualities = $conn->query("SELECT * FROM qualities ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$all_products = $conn->query("SELECT id, name, brand, category, quality FROM products WHERE is_deleted=0 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="filter-group">
            <select id="brand-filter" class="select2-simple">
                <option value="">— All Brands —</option>
                <?php foreach($brands as $b): ?>
                    <option value="<?= htmlspecialchars($b['brand']) ?>" <?= $brand_f == $b['brand'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="category-filter" class="select2-simple">
                <option value="">— All Categories —</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']) ?>" <?= $cat_f == $c['name'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="quality-filter" class="select2-simple">
                <option value="">— All Qualities —</option>
                <?php foreach($qualities as $q): ?>
                    <option value="<?= htmlspecialchars($q['name']) ?>" <?= $qual_f == $q['name'] ? 'selected' : '' ?>><?= htmlspecialchars($q['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="resetFilters()" class="btn btn-ghost btn-sm">Reset</button>
        </div>
        <div class="search-box-wrapper" style="margin-top: 10px; max-width: 300px;">
            <input type="text" id="quick-find" placeholder="Instant find model in list..." style="height: 32px; font-size: 0.8rem;">
        </div>
    </div>
    <div class="toolbar-right no-print">
        <div class="filter-group" style="background: var(--surface-2); padding: 5px 10px; border-radius: var(--radius-sm); border: 1px dashed var(--border-l);">
            <span class="small dim bold uppercase">Quick Adjust:</span>
            <input type="number" id="adjust-percent" placeholder="5" style="width: 60px; height: 30px; font-size: 0.8rem;">
            <span class="small dim">%</span>
            <button type="button" onclick="applyBulkAdjustment('selling')" class="btn btn-xs btn-outline">Apply to Selling</button>
            <button type="button" onclick="applyBulkAdjustment('cost')" class="btn btn-xs btn-ghost">Apply to Cost</button>
        </div>
    </div>
    <div class="toolbar-right">
        <div class="filter-group" style="background: var(--surface-2); padding: 8px 12px; border-radius: 8px; border: 1px dashed var(--border);">
            <span class="small dim bold uppercase">Global Supplier:</span>
            <input type="text" id="global-supplier" placeholder="Apply to all rows..." style="width: 150px; height: 30px;">
            <button type="button" onclick="applyGlobalSupplier()" class="btn btn-xs btn-ghost">Apply All</button>
        </div>
        <button type="button" onclick="addNewProductRow()" class="btn btn-primary btn-sm">+ Add New Product Row</button>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
    <input type="hidden" name="bulk_save" value="1">
    <div class="card">
        <div class="table-wrap">
            <table class="data-table" id="stock-table">
                <thead>
                    <tr>
                        <th>Product / Model</th>
                        <th style="width: 80px;">Stock</th>
                        <th style="width: 100px;">Qty Added</th>
                        <th style="width: 150px;">Cost / Unit (₦)</th>
                        <th style="width: 150px;">Sell Price (₦)</th>
                        <th style="width: 180px;">Supplier</th>
                        <th>Notes</th>
                        <th style="width: 120px;">Margin</th>
                    </tr>
                </thead>
                <tbody id="stock-table-body">
                    <?php foreach ($products as $p): ?>
                    <tr class="product-row" data-id="<?= $p['id'] ?>" data-brand="<?= htmlspecialchars($p['brand']) ?>" data-category="<?= htmlspecialchars($p['category']) ?>" data-quality="<?= htmlspecialchars($p['quality']) ?>">
                        <td>
                            <div class="bold">
                                <?= htmlspecialchars($p['name']) ?> <span class="quality-tag-inline">[<?= htmlspecialchars($p['quality']) ?>]</span>
                            </div>
                            <div class="dim small"><?= htmlspecialchars($p['brand']) ?> • <?= htmlspecialchars($p['category']) ?></div>
                        </td>
                        <td><span class="stock-badge <?= $p['current_stock'] > 0 ? 'green' : 'red' ?>"><?= $p['current_stock'] ?></span></td>
                        <td>
                            <input type="number" name="qty[<?= $p['id'] ?>]" placeholder="0" min="0" class="mono stock-qty-input entry-qty" data-id="<?= $p['id'] ?>" style="width:100%">
                        </td>
                        <td>
                            <input type="number" name="cpu[<?= $p['id'] ?>]" value="<?= $p['cost_price'] ?: '' ?>" placeholder="0.00" step="0.01" class="mono entry-cpu" data-id="<?= $p['id'] ?>" style="width:100%">
                        </td>
                        <td>
                            <input type="number" name="sell[<?= $p['id'] ?>]" value="<?= $p['selling_price'] ?: '' ?>" placeholder="0.00" step="0.01" class="mono entry-sell accent-text" data-id="<?= $p['id'] ?>" style="width:100%">
                        </td>
                        <td>
                            <input type="text" name="supplier[<?= $p['id'] ?>]" class="supplier-field" placeholder="Source..." style="width:100%">
                        </td>
                        <td>
                            <input type="text" name="notes[<?= $p['id'] ?>]" placeholder="..." style="width:100%">
                        </td>
                        <td class="mono small">
                            <?php 
                                $margin = $p['selling_price'] - $p['cost_price'];
                                $pct = calculateMargin($p['cost_price'], $p['selling_price']);
                            ?>
                            <span class="row-margin-display <?= $margin >= 0 ? 'green-text' : 'red-text' ?>">
                                <?= money($margin) ?> (<?= round($pct) ?>%)
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer" style="padding: 20px; position: sticky; bottom: 0; background: var(--surface); border-top: 2px solid var(--border); display: flex; justify-content: flex-end;">
            <div id="batch-summary" class="mono bold" style="margin-right: auto; align-self: center; color: var(--accent);">
                Batch Total: ₦<span id="batch-total-val">0.00</span>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Process all entered stock items?')">Process Stock Inflow</button>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    const categories = <?= json_encode($categories) ?>;
    const qualities = <?= json_encode($qualities) ?>;
    const brandsList = <?= json_encode($brands) ?>;
    const allProductsList = <?= json_encode($all_products) ?>;
    const inventoryData = <?= json_encode($products) ?>;

    $('.select2-simple').select2({
        width: '200px', // Adjusted width to match bulk_prices.php
        closeOnSelect: true
    });

    // Cascading Filter Logic
    function updateFilterDropdowns() {
        const brand = $('#brand-filter').val();
        const cat = $('#category-filter').val();
        const qual = $('#quality-filter').val();

        // Update Brands based on Cat/Qual
        const availableBrands = new Set(inventoryData.filter(p => 
            (!cat || p.category === cat) && (!qual || p.quality === qual)
        ).map(p => p.brand));
        $('#brand-filter option').each(function() {
            if (this.value) $(this).prop('disabled', !availableBrands.has(this.value));
        });

        // Update Categories based on Brand/Qual
        const availableCats = new Set(inventoryData.filter(p => 
            (!brand || p.brand === brand) && (!qual || p.quality === qual)
        ).map(p => p.category));
        $('#category-filter option').each(function() {
            if (this.value) $(this).prop('disabled', !availableCats.has(this.value));
        });

        // Update Qualities based on Brand/Cat
        const availableQuals = new Set(inventoryData.filter(p => 
            (!brand || p.brand === brand) && (!cat || p.category === cat)
        ).map(p => p.quality));
        $('#quality-filter option').each(function() {
            if (this.value) $(this).prop('disabled', !availableQuals.has(this.value));
        });

        $('.select2-simple').trigger('change.select2');
    }

    function filterTable() {
        const brand = $('#brand-filter').val();
        const cat = $('#category-filter').val();
        const qual = $('#quality-filter').val();

        $('.product-row').each(function() {
            const $row = $(this);
            const bMatch = !brand || $row.data('brand') === brand;
            const cMatch = !cat || $row.data('category') === cat;
            const qMatch = !qual || $row.data('quality') === qual;

            $row.toggle(bMatch && cMatch && qMatch);
        });

        updateFilterDropdowns();
    }

    $('#brand-filter, #category-filter, #quality-filter').on('change', filterTable);

    window.resetFilters = function() {
        $('#brand-filter, #category-filter, #quality-filter').val('').trigger('change.select2');
        $('.product-row').show();
        $('#brand-filter option, #category-filter option, #quality-filter option').prop('disabled', false);
        $('.select2-simple').trigger('change.select2');
    };

    // If restock product is requested via URL
    <?php if ($prod_id): ?>
        $('.product-row').hide();
        $('.product-row[data-id="<?= $prod_id ?>"]').show();
    <?php endif; ?>
    
    $(document).on('input', '.stock-qty-input, .new-entry-qty, .entry-cpu, .entry-sell, .new-entry-cpu, .new-entry-sell', function() {
        $(this).closest('tr').css('background', $(this).val() > 0 ? 'rgba(var(--accent-rgb), 0.05)' : '');
        calculateBatchTotal();
    });

    $(document).on('input', '.entry-cpu, .new-entry-cpu', calculateBatchTotal);

    function calculateBatchTotal() {
        let grandTotal = 0;
        
        $('#stock-table-body tr').each(function() {
            const $row = $(this);
            const qty = parseFloat($row.find('.entry-qty, .new-entry-qty').val()) || 0;
            const cpu = parseFloat($row.find('.entry-cpu, .new-entry-cpu').val()) || 0;
            const sell = parseFloat($row.find('.entry-sell, .new-entry-sell').val()) || 0;
            
            grandTotal += (qty * cpu);

            // Live Margin Update
            const margin = sell - cpu;
            const pct = sell > 0 ? Math.round((margin / sell) * 100) : 0;
            const $mDisplay = $row.find('.row-margin-display');
            
            if ($mDisplay.length) {
                const fmt = '₦' + margin.toLocaleString(undefined, {minimumFractionDigits: 2});
                $mDisplay.text(`${fmt} (${pct}%)`);
                $mDisplay.removeClass('green-text red-text').addClass(margin >= 0 ? 'green-text' : 'red-text');
            }
        });

        $('#batch-total-val').text(grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2}));
    }

    // Instant Table Search
    $('#quick-find').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $(".data-table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    window.applyGlobalSupplier = function() {
        const val = $('#global-supplier').val();
        $('.supplier-field').val(val);
    };

    window.applyBulkAdjustment = function(type) {
        const percent = parseFloat($('#adjust-percent').val());
        if (isNaN(percent)) return;
        
        // Adjust selector to match input names in bulk_stock.php
        const selector = type === 'selling' ? 'input[name*="sell"]' : 'input[name*="cpu"]';
        $(selector).each(function() {
            let val = parseFloat($(this).val()) || 0;
            if (val > 0) {
                let newVal = val + (val * (percent / 100));
                $(this).val(newVal.toFixed(2)).trigger('input'); // Trigger input to update highlighting
            }
        });
    };

    window.addNewProductRow = function() {
        let catOptions = '<option value="" disabled selected>— Category —</option>' + categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
        let brandOptions = '<option value="" disabled selected>— Brand —</option>' + brandsList.map(b => `<option value="${b.brand}">${b.brand}</option>`).join('');
        let qualOptions = '<option value="" disabled selected>— Quality —</option>' + qualities.map(q => `<option value="${q.name}" data-category="${q.category_name || ''}">${q.name}</option>`).join('');
        
        let prodOptions = '<option value=""></option>' + allProductsList.map(p => 
            `<option value="${p.name}" data-brand="${p.brand}" data-cat="${p.category}" data-qual="${p.quality}">${p.brand} ${p.name} [${p.quality}]</option>`
        ).join('');

        const rowHtml = `
            <tr class="new-product-row" style="background: rgba(0, 212, 170, 0.08);">
                <td>
                    <div class="form-group" style="margin-bottom:8px">
                        <label class="small bold accent-text" style="font-size: 0.65rem; margin-bottom: 2px; display: block;">MODEL / NAME *</label>
                        <select name="new_name[]" class="new-name-select" required style="width:100%;">
                            ${prodOptions}
                        </select>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap: wrap;">
                        <div style="flex:1; min-width: 80px;">
                            <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">BRAND</label>
                            <select name="new_brand[]" class="new-brand-select" style="width:100%; font-size:0.75rem;">${brandOptions}</select>
                        </div>
                        <div style="flex:1; min-width: 80px;">
                            <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">CATEGORY</label>
                            <select name="new_cat[]" style="width:100%; font-size:0.75rem; height:30px; padding: 0 5px;">${catOptions}</select>
                        </div>
                        <div style="flex:1; min-width: 80px;">
                            <label class="small dim quality-label" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">QUALITY</label>
                            <select name="new_qual[]" style="width:100%; font-size:0.75rem; height:30px; padding: 0 5px;">${qualOptions}</select>
                        </div>
                    </div>
                </td>
                <td><span class="tag">NEW</span></td>
                <td>
                    <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">QTY</label>
                    <input type="number" name="new_qty[]" placeholder="0" min="1" class="mono new-entry-qty" style="width:100%; border-color:var(--accent);">
                </td>
                <td>
                    <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">COST (₦)</label>
                    <input type="number" name="new_cpu[]" placeholder="0.00" step="0.01" class="mono new-entry-cpu" style="width:100%;">
                </td>
                <td>
                    <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">SELL (₦)</label>
                    <input type="number" name="new_sell[]" placeholder="0.00" step="0.01" class="mono accent-text new-entry-sell" style="width:100%; border-color:var(--accent-dim);">
                </td>
                <td>
                    <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">SUPPLIER</label>
                    <input type="text" name="new_supplier[]" class="supplier-field" placeholder="Source..." style="width:100%">
                </td>
                <td>
                    <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px;">NOTES</label>
                    <input type="text" name="new_notes[]" placeholder="..." style="width:100%">
                </td>
                <td>
                    <label class="small dim" style="font-size: 0.6rem; display: block; margin-bottom: 2px; text-align: right;">ACTION</label>
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:10px;">
                        <span class="mono small row-margin-display green-text">₦0.00 (0%)</span>
                        <button type="button" class="btn btn-xs btn-danger" onclick="$(this).closest('tr').remove(); calculateBatchTotal();" title="Remove row">×</button>
                    </div>
                </td>
            </tr>
        `;
        $('#stock-table-body').prepend(rowHtml);

        // Initialize Select2 on the new dropdown
        const $newSelect = $('#stock-table-body tr:first-child .new-name-select');
        $newSelect.select2({
            tags: true,
            placeholder: "Search existing or type new...",
            width: '100%'
        });

        const $newBrandSelect = $('#stock-table-body tr:first-child .new-brand-select');
        $newBrandSelect.select2({
            tags: true,
            placeholder: "Select or type...",
            width: '100%'
        });

        // Handle auto-fill when an existing product is selected
        $newSelect.on('select2:select', function(e) {
            const data = e.params.data;
            const $row = $(this).closest('tr');
            
            if (data.element) {
                const $el = $(data.element);
                $row.find('select[name="new_brand[]"]').val($el.data('brand')).trigger('change');
                $row.find('select[name="new_cat[]"]').val($el.data('cat')).trigger('change');
                $row.find('select[name="new_qual[]"]').val($el.data('qual'));
            }
        });
    };

    // Dynamic Filtering for New Product Rows
    $(document).on('change', 'select[name="new_cat[]"]', function() {
        const row = $(this).closest('tr');
        const cat = $(this).val();
        const $qualitySelect = row.find('select[name="new_qual[]"]');
        const $label = row.find('.quality-label');

        // Dynamic Attribute Labeling
        const labelMap = {
            'Backglass': 'COLOR / FINISH',
            'Housing': 'COLOR / FINISH',
            'Battery Cover': 'COLOR / FINISH',
            'Battery': 'CAPACITY / QUALITY',
            'Screen': 'QUALITY / GRADE'
        };
        $label.text(labelMap[cat] || 'QUALITY / VARIANT');

        $qualitySelect.find('option').each(function() {
            const limitCat = $(this).data('category');
            if (!limitCat || limitCat === "" || limitCat === cat) {
                $(this).show().prop('disabled', false);
            } else {
                $(this).hide().prop('disabled', true);
            }
        });
        $qualitySelect.val($qualitySelect.find('option:first').val());
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>