<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Product Attributes';
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.'; $msg_type = 'error';
    } else {
        // --- Category Logic ---
        if ($_POST['action'] === 'add_cat') {
            $name = trim($_POST['name'] ?? '');
            if ($name) {
                $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                if ($stmt->execute()) { $msg = "Category \"$name\" added."; $msg_type = 'success'; }
                else { $msg = "Error: Category might already exist."; $msg_type = 'error'; }
            }
        }
        if ($_POST['action'] === 'delete_cat') {
            $id = intval($_POST['id']);
            
            // Integrity Check: Is any product using this category name?
            $check = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category = (SELECT name FROM categories WHERE id = ?)");
            $check->bind_param("i", $id);
            $check->execute();
            if ($check->get_result()->fetch_assoc()['count'] > 0) {
                $msg = "Cannot delete: One or more products are currently assigned to this category.";
                $msg_type = 'error';
            } else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Category removed."; $msg_type = 'success';
            }
        }

        // --- Quality Logic ---
        if ($_POST['action'] === 'add_quality') {
            $name = trim($_POST['name'] ?? '');
            $cat_name = trim($_POST['category_name'] ?? '');
            if ($cat_name === 'Global') $cat_name = null;

            if ($name) {
                $stmt = $conn->prepare("INSERT INTO qualities (name, category_name) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $cat_name);
                if ($stmt->execute()) { $msg = "Quality grade \"$name\" added."; $msg_type = 'success'; }
                else { $msg = "Error: Quality type might already exist."; $msg_type = 'error'; }
            }
        }
        if ($_POST['action'] === 'delete_quality') {
            $id = intval($_POST['id']);
            
            // Integrity Check: Is any product using this quality name?
            $check = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE quality = (SELECT name FROM qualities WHERE id = ?)");
            $check->bind_param("i", $id);
            $check->execute();
            if ($check->get_result()->fetch_assoc()['count'] > 0) {
                $msg = "Cannot delete: One or more products are currently assigned to this quality grade.";
                $msg_type = 'error';
            } else {
                $stmt = $conn->prepare("DELETE FROM qualities WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $msg = "Quality type removed."; $msg_type = 'success';
            }
        }
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$qualities = $conn->query("SELECT * FROM qualities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="two-col">
    <!-- Categories Section -->
    <section>
        <div class="card form-card">
            <div class="card-header"><h3>Manage Part Categories</h3></div>
            <form method="POST">
                <input type="hidden" name="action" value="add_cat">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" placeholder="e.g. LCD Screen, Battery..." required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Add Category →</button>
            </form>
            <div class="card-divider"></div>
            <div class="table-wrap">
                <table class="data-table small">
                    <thead><tr><th>Category</th><th style="text-align:right">Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="bold accent-text"><?= htmlspecialchars($cat['name']) ?></td>
                            <td style="text-align:right">
                                <form method="POST" onsubmit="return confirm('Delete category?')">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                    <input type="hidden" name="action" value="delete_cat"><input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger">×</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Qualities Section -->
    <section>
        <div class="card form-card">
            <div class="card-header"><h3>Manage Quality Grades</h3></div>
            <form method="POST">
                <input type="hidden" name="action" value="add_quality">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <div class="form-group">
                    <label>Variant / Quality Name</label>
                    <input type="text" name="name" placeholder="e.g. OLED, Full Glass, Midnight Black..." required>
                </div>
                <div class="form-group">
                    <label>Restrict to Category</label>
                    <select name="category_name">
                        <option value="Global">Available for All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Choose "Global" if this applies to everything.</span>
                </div>
                <button type="submit" class="btn btn-primary btn-full" style="background: var(--blue); color: #fff;">Add Quality →</button>
            </form>
            <div class="card-divider"></div>
            <div class="table-wrap">
                <table class="data-table small">
                    <thead><tr><th>Variant/Grade</th><th>Category</th><th style="text-align:right">Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($qualities as $q): ?>
                        <tr>
                            <td class="bold" style="color: var(--blue);"><?= htmlspecialchars($q['name']) ?></td>
                            <td class="dim"><?= htmlspecialchars($q['category_name'] ?? 'Global') ?></td>
                            <td style="text-align:right">
                                <form method="POST" onsubmit="return confirm('Delete quality?')">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                    <input type="hidden" name="action" value="delete_quality"><input type="hidden" name="id" value="<?= $q['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger">×</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?>