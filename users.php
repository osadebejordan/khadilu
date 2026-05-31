<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Security: Only admins can manage users
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'User Management';
$msg = '';
$msg_type = '';

// --- Fetch User for Editing ---
$edit_user_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT id, full_name, username, role, permissions FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_user_data = $stmt->get_result()->fetch_assoc();
    
    // Convert comma string back to array for checkbox logic
    $edit_user_data['perms_arr'] = !empty($edit_user_data['permissions']) ? explode(',', $edit_user_data['permissions']) : [];
}

// Handle User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Security Validation Failed.");
        }

        if ($_POST['action'] === 'add_user') {
            $full_name = trim($_POST['full_name'] ?? '');
            $username  = trim($_POST['username'] ?? '');
            $password  = $_POST['password'] ?? '';
            $role      = $_POST['role'] ?? 'staff';
            $perms     = isset($_POST['perms']) ? implode(',', $_POST['perms']) : '';

            if (!$full_name || !$username || !$password) {
                throw new Exception("All fields are required.");
            }
            
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, permissions) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $username, $hashed, $role, $perms);
            
            if ($stmt->execute()) {
                $msg = "User \"" . htmlspecialchars($username) . "\" created successfully."; 
                $msg_type = 'success';
            }
        }

        if ($_POST['action'] === 'update_user') {
            $uid       = intval($_POST['user_id']);
            $full_name = trim($_POST['full_name'] ?? '');
            $role      = $_POST['role'] ?? 'staff';
            $perms     = isset($_POST['perms']) ? implode(',', $_POST['perms']) : '';
            $password  = $_POST['password'] ?? '';

            if (!$full_name) throw new Exception("Full Name is required.");

            // If password is provided, we update it, otherwise we leave it alone
            if (!empty($password)) {
                if (strlen($password) < 6) throw new Exception("Password must be at least 6 characters.");
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, role = ?, permissions = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $full_name, $role, $perms, $hashed, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, role = ?, permissions = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $role, $perms, $uid);
            }

            if ($stmt->execute()) {
                logActivity('UPDATE_USER_PERMS', 'users', $uid, "Updated permissions/role for $full_name");
                header("Location: users.php?msg=updated");
                exit();
            }
        }

        if ($_POST['action'] === 'delete_user') {
            $id = intval($_POST['user_id']);
            if ($id === $_SESSION['khadilu_user_id']) {
                throw new Exception("You cannot delete your own account.");
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $msg = "User deleted."; $msg_type = 'success';
        }
    } catch (Exception $e) {
        log_error($e, 'USER_MANAGEMENT');
        $msg = "Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

$users = $conn->query("SELECT id, full_name, username, role, permissions FROM users ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);
require_once 'includes/header.php';

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    echo renderToast("User updated successfully.", "success");
}
?>

<?= renderToast($msg, $msg_type) ?>

<div class="two-col">
    <!-- Registration Form -->
    <div class="card form-card" id="user-form-card">
        <div class="card-header"><h3><?= $edit_user_data ? 'Edit User: ' . htmlspecialchars($edit_user_data['full_name']) : 'Register New Staff/Admin' ?></h3></div>
        <form method="POST" autocomplete="off" id="userForm">
            <input type="hidden" name="action" value="<?= $edit_user_data ? 'update_user' : 'add_user' ?>">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <?php if ($edit_user_data): ?><input type="hidden" name="user_id" value="<?= $edit_user_data['id'] ?>"><?php endif; ?>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($edit_user_data['full_name'] ?? '') ?>" placeholder="e.g. John Doe" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($edit_user_data['username'] ?? '') ?>" placeholder="e.g. jdoe" <?= $edit_user_data ? 'readonly' : 'required' ?> autocomplete="new-username">
                <?php if ($edit_user_data): ?><span class="form-hint">Username cannot be changed.</span><?php endif; ?>
            </div>
            <div class="form-group">
                <label><?= $edit_user_data ? 'Update Password' : 'Password' ?></label>
                <input type="password" name="password" placeholder="<?= $edit_user_data ? 'Leave blank to keep current' : 'Min. 6 characters' ?>" <?= $edit_user_data ? '' : 'required' ?> autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="roleSelect">
                    <option value="staff" <?= ($edit_user_data['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff (Sales & Stock only)</option>
                    <option value="sub-admin" <?= ($edit_user_data['role'] ?? '') === 'sub-admin' ? 'selected' : '' ?>>Sub-Admin (Custom Access)</option>
                    <option value="admin" <?= ($edit_user_data['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin (Full Access)</option>
                </select>
            </div>

            <div id="permission-zone" style="display: <?= ($edit_user_data['role'] ?? '') === 'sub-admin' ? 'block' : 'none' ?>; background: var(--surface-2); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px dashed var(--border);">
                <label class="small bold accent-text" style="display:block; margin-bottom:10px;">Grant Sub-Admin Privileges:</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <?php 
                    $available_perms = [
                        'manage_inventory' => 'Manage Inventory',
                        'view_costs'       => 'View Cost Prices',
                        'manage_repairs'   => 'Manage Repairs',
                        'delete_records'   => 'Delete Records',
                        'view_reports'     => 'View History/Reports'
                    ];
                    foreach ($available_perms as $val => $label): 
                        $checked = in_array($val, $edit_user_data['perms_arr'] ?? []) ? 'checked' : '';
                    ?>
                        <label class="small"><input type="checkbox" name="perms[]" value="<?= $val ?>" <?= $checked ?>> <?= $label ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full"><?= $edit_user_data ? 'Update User' : 'Create Account' ?></button>
            <?php if ($edit_user_data): ?>
                <a href="users.php" class="btn btn-ghost btn-full" style="margin-top:10px;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <script>
    $('#roleSelect').on('change', function() {
        $('#permission-zone').toggle($(this).val() === 'sub-admin');
    });
    </script>

    <!-- Users List -->
    <div class="card">
        <div class="card-header"><h3>Current Users</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Permissions</th><th style="text-align:right">Action</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr class="<?= isset($edit_id) && $edit_id === $u['id'] ? 'highlight-row' : '' ?>">
                        <td class="bold">
                            <a href="users.php?edit=<?= $u['id'] ?>#user-form-card" class="user-edit-link">
                                <?= htmlspecialchars($u['full_name']) ?>
                            </a>
                        </td>
                        <td class="mono small"><?= htmlspecialchars($u['username']) ?></td>
                        <td><span class="tag"><?= ucfirst($u['role']) ?></span></td>
                        <td class="small dim" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= $u['role'] === 'admin' ? 'Full' : ($u['permissions'] ? str_replace('_', ' ', $u['permissions']) : 'None') ?>
                        </td>
                        <td style="text-align:right">
                            <div class="action-btns" style="justify-content: flex-end;">
                            <a href="users.php?edit=<?= $u['id'] ?>" class="btn btn-xs btn-ghost">Edit</a>
                            <?php if ($u['id'] !== $_SESSION['khadilu_user_id']): ?>
                            <form method="POST" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger">Remove</button>
                            </form>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>