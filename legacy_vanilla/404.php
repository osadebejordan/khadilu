<?php
require_once 'config.php';
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
    <title>404 - Not Found | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">

    <div class="auth-bg-grid"></div>

    <div class="auth-wrap">
        <div class="auth-card" style="text-align: center;">
            <div class="logo-badge" style="font-size: 4rem; margin-bottom: 20px;">🕵️‍♂️</div>
            <h1 style="font-size: 4rem; color: var(--accent); margin-bottom: 10px; font-family: var(--font-heading);">404</h1>
            <h2 style="margin-bottom: 15px; font-family: var(--font-heading);">Lost in the Hub?</h2>
            <p class="dim" style="margin-bottom: 30px; line-height: 1.6;">The page you are looking for doesn't exist or has been moved to a different sector of the system.</p>

            <a href="dashboard.php" class="btn btn-primary btn-full">Return to Command Centre</a>
        </div>
    </div>

</body>

</html>