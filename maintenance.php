<?php
/**
 * Maintenance Mode Screen for Khadilu
 */
require_once 'config.php';

$error = '';

// Handle Engineer Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['engineer_login'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed.";
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        // Specialized Engineer Credentials
        if (defined('ENG_USER') && $user === ENG_USER && $pass === ENG_PASS) {
            session_regenerate_id(true);
            $_SESSION['khadilu_user_id'] = 0; // System ID
            $_SESSION['khadilu_username'] = 'engineer';
            $_SESSION['khadilu_full_name'] = 'Systems Engineer';
            $_SESSION['khadilu_role'] = 'admin';
            $_SESSION['last_activity'] = time();
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid engineer credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance | Khadilu</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --accent: #00d4aa;
            --text: #f8fafc;
            --dim: #94a3b8;
            --yellow: #eab308;
            --red: #ff6b6b;
        }
        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Nunito Sans', sans-serif;
            text-align: center;
            overflow-x: hidden;
        }
        .container {
            max-width: 500px;
            padding: 2rem;
            z-index: 2;
        }
        .icon-wrapper {
            font-size: 80px;
            margin-bottom: 20px;
            display: inline-block;
            filter: drop-shadow(0 0 15px rgba(0, 212, 170, 0.3));
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(-5deg); }
            50% { transform: translateY(-20px) rotate(15deg); }
        }
        h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 10px 0;
            background: linear-gradient(45deg, var(--text), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            color: var(--dim);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .loader-bar {
            height: 6px;
            width: 100%;
            background: var(--card);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            margin-bottom: 40px;
        }
        .loader-progress {
            position: absolute;
            height: 100%;
            width: 40%;
            background: var(--accent);
            border-radius: 10px;
            box-shadow: 0 0 20px var(--accent);
            animation: sweep 2s infinite ease-in-out;
        }
        @keyframes sweep {
            0% { left: -40%; }
            100% { left: 100%; }
        }
        .engineer-zone {
            background: var(--card);
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        input {
            background: var(--bg);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            width: 80%;
        }
        .btn-eng {
            background: var(--accent);
            color: var(--bg);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-wrapper">🛠️</div>
        <h1>Khadilu Phone Hub</h1>
        <p>System Maintenance: We are currently performing scheduled maintenance. Only administrative staff can access the system at this time.</p>
        <div class="loader-bar"><div class="loader-progress"></div></div>

        <div class="engineer-zone">
            <h3 style="margin-top:0; font-size: 0.9rem; color: var(--accent); text-transform: uppercase; letter-spacing: 1px;">Engineer Portal</h3>
            <?php if ($error): ?><div style="color:var(--red); font-size: 0.8rem; margin-bottom:10px;"><?= $error ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="text" name="username" placeholder="Engineer ID" required>
                <input type="password" name="password" placeholder="Access Key" required>
                <button type="submit" name="engineer_login" class="btn-eng">Enter System →</button>
            </form>
        </div>
    </div>
</body>
</html>