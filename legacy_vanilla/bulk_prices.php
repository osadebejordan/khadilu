<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Require permission to manage inventory
if (!can('manage_inventory')) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Bulk Price Updates';
$msg = '';
$msg_type = '';

$can_view_costs = can('view_costs');

// Handle Price updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_prices_save'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.';
        $msg_type = 'error';
    } else {
        try {
            $conn->begin_transaction();
            $processed_count = 0;

            if (isset($_POST['sell']) && is_array($_POST['sell'])) {
                if ($can_view_costs) {
                    $stmt = $conn->prepare("UPDATE products SET cost_price = ?, selling_price = ?, updated_at = NOW() WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE products SET selling_price = ?, updated_at = NOW() WHERE id = ?");
                }

                foreach ($_POST['sell'] as $id => $sell_val) {
                    $id = intval($id);
                    $sell = floatval($sell_val);
                    if ($sell < 0) continue;

                    // Fetch existing prices to check if they actually changed (optional check, but good for logs)
                    if ($can_view_costs) {
                        $cpu = floatval($_POST['cpu'][$id] ?? 0);
                        if ($cpu < 0) $cpu = 0.00;
                        $stmt->bind_param("ddi", $cpu, $sell, $id);
                    } else {
                        $stmt->bind_param("di", $sell, $id);
                    }
                    $stmt->execute();
                    $processed_count++;
                }
            }

            if ($processed_count > 0) {
                $conn->commit();
                logActivity('BULK_PRICE_UPDATE', 'products', null, "Bulk updated prices for $processed_count items.");
                $msg = "✓ Success! Prices updated for $processed_count items.";
                $msg_type = 'success';
            } else {
                $conn->rollback();
                $msg = "No valid prices were modified.";
                $msg_type = 'error';
            }
        } catch (Exception $e) {
            $conn->rollback();
            log_error($e, 'BULK_PRICE_UPDATE');
            $msg = "Error: " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// Fetch all active products for rendering
$sql = "SELECT id, name, brand, category, quality, cost_price, selling_price, current_stock FROM products WHERE is_deleted = 0 ORDER BY brand ASC, name ASC";
$products = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$brands = $conn->query("SELECT DISTINCT brand FROM products WHERE is_deleted=0 AND brand IS NOT NULL ORDER BY brand")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$qualities = $conn->query("SELECT * FROM qualities ORDER BY name")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="filter-group">
            <select id="brand-filter" class="select2-simple">
                <option value="">— All Brands —</option>
                <?php foreach($brands as $b): ?>
                    <option value="<?= htmlspecialchars($b['brand']) ?>"><?= htmlspecialchars($b['brand']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="category-filter" class="select2-simple">
                <option value="">— All Categories —</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="quality-filter" class="select2-simple">
                <option value="">— All Qualities —</option>
                <?php foreach($qualities as $q): ?>
                    <option value="<?= htmlspecialchars($q['name']) ?>"><?= htmlspecialchars($q['name']) ?></option>
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
            <select id="adjust-type" style="width: 110px; height: 30px; padding: 0 5px; font-size: 0.8rem; border-radius: 4px;">
                <option value="selling">Selling Price</option>
                <?php if ($can_view_costs): ?>
                    <option value="cost">Cost Price</option>
                    <option value="both">Both</option>
                <?php endif; ?>
            </select>
            <button type="button" onclick="applyBulkAdjustment()" class="btn btn-xs btn-primary">Apply to Visible</button>
        </div>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
    <input type="hidden" name="bulk_prices_save" value="1">
    <div class="card">
        <div class="table-wrap">
            <table class="data-table" id="prices-table">
                <thead>
                    <tr>
                        <th>Product / Model</th>
                        <th style="width: 80px;">Stock</th>
                        <?php if ($can_view_costs): ?>
                            <th style="width: 200px;">Cost Price (₦)</th>
                        <?php endif; ?>
                        <th style="width: 200px;">Selling Price (₦)</th>
                        <?php if ($can_view_costs): ?>
                            <th style="width: 150px;">Margin</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="prices-table-body">
                    <?php foreach ($products as $p): ?>
                    <tr class="product-row" data-id="<?= $p['id'] ?>" data-brand="<?= htmlspecialchars($p['brand']) ?>" data-category="<?= htmlspecialchars($p['category']) ?>" data-quality="<?= htmlspecialchars($p['quality']) ?>">
                        <td>
                            <div class="bold">
                                <?= htmlspecialchars($p['name']) ?> <span class="quality-tag-inline">[<?= htmlspecialchars($p['quality']) ?>]</span>
                            </div>
                            <div class="dim small"><?= htmlspecialchars($p['brand']) ?> • <?= htmlspecialchars($p['category']) ?></div>
                        </td>
                        <td><span class="stock-badge <?= $p['current_stock'] > 0 ? 'green' : 'red' ?>"><?= $p['current_stock'] ?></span></td>
                        
                        <?php if ($can_view_costs): ?>
                        <td>
                            <input type="number" name="cpu[<?= $p['id'] ?>]" value="<?= htmlspecialchars($p['cost_price']) ?>" placeholder="0.00" step="0.01" min="0" class="mono entry-cpu" data-id="<?= $p['id'] ?>" style="width:100%">
                        </td>
                        <?php endif; ?>

                        <td>
                            <input type="number" name="sell[<?= $p['id'] ?>]" value="<?= htmlspecialchars($p['selling_price']) ?>" placeholder="0.00" step="0.01" min="0" class="mono entry-sell accent-text" data-id="<?= $p['id'] ?>" style="width:100%">
                        </td>

                        <?php if ($can_view_costs): ?>
                        <td class="mono small">
                            <?php 
                                $margin = $p['selling_price'] - $p['cost_price'];
                                $pct = calculateMargin($p['cost_price'], $p['selling_price']);
                            ?>
                            <span class="row-margin-display <?= $margin >= 0 ? 'green-text' : 'red-text' ?>">
                                <?= money($margin) ?> (<?= round($pct) ?>%)
                            </span>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer" style="padding: 20px; position: sticky; bottom: 0; background: var(--surface); border-top: 2px solid var(--border); display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Save all modifications to product prices?')">Save Price Modifications</button>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    const inventoryData = <?= json_encode($products) ?>;
    const canViewCosts = <?= $can_view_costs ? 'true' : 'false' ?>;

    $('.select2-simple').select2({
        width: '200px',
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

    // Instant Search
    $('#quick-find').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $("#prices-table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // Row highlights and calculations on edit
    $(document).on('input', '.entry-cpu, .entry-sell', function() {
        const $row = $(this).closest('tr');
        $row.css('background', 'rgba(var(--accent-rgb), 0.05)');
        recalculateRowMargin($row);
    });

    function recalculateRowMargin($row) {
        if (!canViewCosts) return;
        
        const cpuInput = $row.find('.entry-cpu');
        const sellInput = $row.find('.entry-sell');
        const display = $row.find('.row-margin-display');

        const cpu = parseFloat(cpuInput.val()) || 0;
        const sell = parseFloat(sellInput.val()) || 0;

        const margin = sell - cpu;
        const pct = sell > 0 ? Math.round((margin / sell) * 100) : 0;

        const fmt = '₦' + margin.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        display.text(`${fmt} (${pct}%)`);
        display.removeClass('green-text red-text').addClass(margin >= 0 ? 'green-text' : 'red-text');
    }

    // Apply Bulk Percentage Adjustments to Visible Rows
    window.applyBulkAdjustment = function() {
        const percent = parseFloat($('#adjust-percent').val());
        if (isNaN(percent)) return;

        const type = $('#adjust-type').val(); // 'selling', 'cost', 'both'

        $('.product-row:visible').each(function() {
            const $row = $(this);
            let hasChanged = false;

            if (type === 'selling' || type === 'both') {
                const $sellInput = $row.find('.entry-sell');
                let val = parseFloat($sellInput.val()) || 0;
                if (val > 0) {
                    let newVal = val + (val * (percent / 100));
                    $sellInput.val(newVal.toFixed(2));
                    hasChanged = true;
                }
            }

            if (canViewCosts && (type === 'cost' || type === 'both')) {
                const $cpuInput = $row.find('.entry-cpu');
                let val = parseFloat($cpuInput.val()) || 0;
                if (val > 0) {
                    let newVal = val + (val * (percent / 100));
                    $cpuInput.val(newVal.toFixed(2));
                    hasChanged = true;
                }
            }

            if (hasChanged) {
                $row.css('background', 'rgba(var(--accent-rgb), 0.05)');
                recalculateRowMargin($row);
            }
        });
    };
});
</script>

<?php require_once 'includes/footer.php'; ?>
