<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$user = currentUser();
$page_title = 'My Profile';
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stored_pass = $stmt->get_result()->fetch_assoc()['password'];

    if (!password_verify($current_pass, $stored_pass)) {
        $msg = "Current password is incorrect."; $msg_type = "error";
    } elseif ($new_pass !== $confirm_pass) {
        $msg = "New passwords do not match."; $msg_type = "error";
    } elseif (strlen($new_pass) < 6) {
        $msg = "New password must be at least 6 characters."; $msg_type = "error";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $hashed, $user['id']);
        $upd->execute();
        $msg = "Password updated successfully!"; $msg_type = "success";
    }
}

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="card" style="max-width: 500px;">
    <div class="card-header"><h3>Update Password</h3></div>
    <form method="POST" style="padding:20px;">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Update Password</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>