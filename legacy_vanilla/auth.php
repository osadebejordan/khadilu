<?php
// =============================================
//  Khadilu Phone Hub — Auth Functions
// =============================================

// ┌─────────────────────────────────────────────┐
// │  TEMPORARY LOGIN BYPASS FOR TESTING          │
// │  Set to 'true' to skip login requirement.    │
// │  Set to 'false' to re-enable login.          │
// └─────────────────────────────────────────────┘
define('SKIP_LOGIN', false);

if (SKIP_LOGIN && !isset($_SESSION['khadilu_user_id'])) {
    // Auto-authenticate as admin for full access during testing
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $_SESSION['khadilu_user_id']    = 1;
    $_SESSION['khadilu_username']   = 'admin';
    $_SESSION['khadilu_full_name']  = 'Test Admin';
    $_SESSION['khadilu_role']       = 'admin';
    $_SESSION['khadilu_permissions'] = [];
}

function isLoggedIn() {
    if (defined('SKIP_LOGIN') && SKIP_LOGIN) return true;
    return isset($_SESSION['khadilu_user_id']);
}

function requireLogin() {
    if (defined('SKIP_LOGIN') && SKIP_LOGIN) return;
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

function isAdmin() {
    return ($_SESSION['khadilu_role'] ?? '') === 'admin';
}

function isSubAdmin() {
    return ($_SESSION['khadilu_role'] ?? '') === 'sub-admin';
}

/**
 * Core Permission Checker
 * Returns true if user is Super Admin OR a Sub-Admin with the specific permission.
 */
function can($permission) {
    // Super Admin has absolute power
    if (isAdmin()) return true;
    
    if (isSubAdmin()) {
        $perms = $_SESSION['khadilu_permissions'] ?? [];
        return in_array($permission, $perms);
    }
    return false;
}

function isStaff() {
    return ($_SESSION['khadilu_role'] ?? '') === 'staff';
}

function currentUser() {
    return [
        'id'        => $_SESSION['khadilu_user_id'] ?? null,
        'username'  => $_SESSION['khadilu_username'] ?? '',
        'full_name' => $_SESSION['khadilu_full_name'] ?? '',
        'role'      => $_SESSION['khadilu_role'] ?? 'staff',
        'permissions' => $_SESSION['khadilu_permissions'] ?? []
    ];
}

// --- Maintenance Mode Logic ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Identify if the current session belongs to the Engineer
$is_engineer = ($_SESSION['khadilu_username'] ?? '') === 'engineer';

// Block everyone except the engineer if maintenance mode is on. 
// We remove index.php from the allowed list so users see the maintenance screen immediately.
if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE && !$is_engineer && $current_page !== 'maintenance.php') {
    include 'maintenance.php';
    exit;
}
?>
