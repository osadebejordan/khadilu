<?php
// =============================================
//  Khadilu Phone Hub — Auth Functions
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['khadilu_user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }

    if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE && !isAdmin()) {
        header("Location: index.php?msg=maintenance");
        exit();
    }
}

function isAdmin() {
    return ($_SESSION['khadilu_role'] ?? '') === 'admin';
}

function currentUser() {
    return [
        'id'        => $_SESSION['khadilu_user_id'] ?? null,
        'username'  => $_SESSION['khadilu_username'] ?? '',
        'full_name' => $_SESSION['khadilu_full_name'] ?? '',
        'role'      => $_SESSION['khadilu_role'] ?? 'staff',
    ];
}

// Generate a CSRF token
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate a CSRF token
function validateCsrfToken($token) {
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
?>
