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

// Handle User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.'; $msg_type = 'error';
    } else {

    if ($_POST['action'] === 'add_user') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = $_POST['role'] ?? 'staff';

        if (!$full_name || !$username || !$password) {
            $msg = 'All fields are required.'; $msg_type = 'error';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $username, $hashed, $role);
            
            if ($stmt->execute()) {
                $msg = "User \"" . htmlspecialchars($username) . "\" created successfully."; $msg_type = 'success';
            } else {
                $msg = 'Error: Username might already exist.'; $msg_type = 'error';
            }
        }
    }

    if ($_POST['action'] === 'delete_user') {
        $id = intval($_POST['user_id']);
        // Prevent deleting yourself
        if ($id === $_SESSION['khadilu_user_id']) {
            $msg = "You cannot delete your own account."; $msg_type = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $msg = "User deleted."; $msg_type = 'success';
        }
    }
    }
}

$users = $conn->query("SELECT id, full_name, username, role FROM users ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);
require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="two-col">
    <!-- Registration Form -->
    <div class="card form-card">
        <div class="card-header"><h3>Register New Staff/Admin</h3></div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" placeholder="e.g. John Doe" required>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="e.g. jdoe" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="staff">Staff (Sales & Stock only)</option>
                    <option value="admin">Admin (Full Access)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Create Account →</button>
        </form>
    </div>

    <!-- Users List -->
    <div class="card">
        <div class="card-header"><h3>Current Users</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="bold"><?= htmlspecialchars($u['full_name']) ?></td>
                        <td class="mono small"><?= htmlspecialchars($u['username']) ?></td>
                        <td><span class="tag"><?= ucfirst($u['role']) ?></span></td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['khadilu_user_id']): ?>
                            <form method="POST" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger">Remove</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>