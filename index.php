<?php
require_once 'config.php';
require_once 'auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'expired') {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

if (isset($_GET['msg']) && $_GET['msg'] === 'maintenance') {
    $error = 'The system is currently under maintenance. Only administrators can access the dashboard at this time.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE && $user['role'] !== 'admin') {
                $error = 'Maintenance Mode: Access is temporarily restricted to Administrators.';
            } else {
                // Security: Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['khadilu_user_id']   = $user['id'];
                $_SESSION['khadilu_username']  = $username;
                $_SESSION['khadilu_full_name'] = $user['full_name'];
                $_SESSION['khadilu_role']      = $user['role'];
                header("Location: dashboard.php");
                exit();
            }
        }
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Khadilu Phone Hub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Nunito+Sans:ital,wght@0,300;0,400;0,600;0,700;1,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">

<div class="auth-bg-grid"></div>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-badge">📱</div>
      <h1 class="logo-title">Khadilu Phone Hub</h1>
      <p class="logo-sub">Inventory Management System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter username" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full">
        Sign In <span class="btn-arrow">→</span>
      </button>
    </form>

  </div>

  <div class="auth-tagline">
    <span>Track every phone.</span>
    <span>Every naira.</span>
    <span>Every time.</span>
  </div>
</div>
</body>
</html>
