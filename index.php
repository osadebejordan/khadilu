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

    $stmt = $conn->prepare("SELECT id, full_name, password, role, permissions FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Security: Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            $_SESSION['khadilu_user_id']   = $user['id'];
            $_SESSION['khadilu_username']  = $username;
            $_SESSION['khadilu_full_name'] = $user['full_name'];
            $_SESSION['khadilu_role']      = $user['role'];
            
            // Permissions are stored as a comma-separated string or JSON in the DB
            $raw_perms = $user['permissions'] ?? '';
            $_SESSION['khadilu_permissions'] = !empty($raw_perms) ? explode(',', $raw_perms) : [];

            header("Location: dashboard.php");
            exit();
        }
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
  (function() {
    const savedTheme = localStorage.getItem('khadilu-theme') || 'theme-business-light';
    document.documentElement.className = savedTheme;
  })();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Khadilu Phone Hub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Nunito+Sans:ital,wght@0,300;0,400;0,600;0,700;1,300&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">

<div class="auth-bg-grid"></div>

<!-- Floating Theme Selector -->
<div class="theme-toggle-login" style="position: absolute; top: 20px; right: 20px; z-index: 100;">
  <select id="loginThemeSelector" class="theme-select" style="width: auto; height: 34px !important; padding: 4px 10px !important; font-size: 0.8rem !important;">
    <option value="theme-business-light">💼 Emerald Prestige</option>
    <option value="theme-corporate-dark">🛠️ Midnight Amber</option>
    <option value="theme-cyber-original">👾 Cyber Command</option>
  </select>
</div>

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
        <div class="password-wrapper">
          <input type="password" id="password" name="password" placeholder="Enter password" autocomplete="current-password" required>
          <span class="toggle-password" id="togglePassword">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </span>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-full">
        Sign In
      </button>
    </form>

  </div>

  <div class="auth-tagline">
    <span>Track every phone.</span>
    <span>Every naira.</span>
    <span>Every time.</span>
  </div>
</div>

<script>
  const togglePassword = document.getElementById('togglePassword');
  const password = document.getElementById('password');
  
  const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
  const eyeOffIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;

  togglePassword.addEventListener('click', function() {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    
    // Toggle SVG icon
    this.innerHTML = type === 'password' ? eyeIcon : eyeOffIcon;
  });

  // Theme Switching Logic
  const loginThemeSelector = document.getElementById('loginThemeSelector');
  const currentTheme = localStorage.getItem('khadilu-theme') || 'theme-business-light';
  loginThemeSelector.value = currentTheme;

  loginThemeSelector.addEventListener('change', function() {
    const selectedTheme = this.value;
    localStorage.setItem('khadilu-theme', selectedTheme);
    document.documentElement.className = selectedTheme;
  });
</script>
</body>
</html>
