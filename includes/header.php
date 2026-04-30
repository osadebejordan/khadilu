<?php
// includes/header.php — shared navigation header
$user = currentUser();
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Low stock count for badge
$low_stock_q = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE current_stock <= low_stock_threshold AND current_stock > 0");
$low_stock_count = ($low_stock_q) ? ($low_stock_q->fetch_assoc()['cnt'] ?? 0) : 0;

$out_of_stock_q = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE current_stock = 0");
$out_of_stock_count = ($out_of_stock_q) ? ($out_of_stock_q->fetch_assoc()['cnt'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?? 'Dashboard' ?> — Khadilu Phone Hub</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<!-- Third Party Stacks -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>

<div id="page-loader" class="global-loader">
  <div class="spinner"></div>
</div>

<script>
  // Hide global loader when page is fully loaded
  window.addEventListener('load', function() {
    const loader = document.getElementById('page-loader');
    loader.style.opacity = '0';
    setTimeout(() => { loader.style.visibility = 'hidden'; }, 400);
  });
</script>

<nav class="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-name">Khadilu</div>
        <div class="sidebar-logo-sub">Phone Hub</div>
      </div>
    </div>
  </div>

  <ul class="nav-links">
    <li>
      <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
        Dashboard
      </a>
    </li>
    <li>
      <a href="inventory.php" class="nav-link <?= $current_page === 'inventory' ? 'active' : '' ?>">
        Inventory
        <?php if ($out_of_stock_count > 0): ?>
          <span class="badge badge-red"><?= $out_of_stock_count ?></span>
        <?php elseif ($low_stock_count > 0): ?>
          <span class="badge badge-yellow"><?= $low_stock_count ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php if (isAdmin()): ?>
    <li>
      <a href="low_stock_report.php" class="nav-link <?= $current_page === 'low_stock_report' ? 'active' : '' ?>">
        Low Stock Report
      </a>
    </li>
    <?php endif; ?>
    <li>
      <a href="add_stock.php" class="nav-link <?= $current_page === 'add_stock' ? 'active' : '' ?>">
        Add Stock
      </a>
    </li>
    <li>
      <a href="record_sale.php" class="nav-link <?= $current_page === 'record_sale' ? 'active' : '' ?>">
        Record Sale
      </a>
    </li>
    <li>
      <a href="repairs.php" class="nav-link <?= $current_page === 'repairs' ? 'active' : '' ?>">
        Repairs
      </a>
    </li>
    <li>
      <a href="customers.php" class="nav-link <?= $current_page === 'customers' ? 'active' : '' ?>">
        Customers & Debts
      </a>
    </li>
    <li>
      <a href="history.php" class="nav-link <?= $current_page === 'history' ? 'active' : '' ?>">
        History
      </a>
    </li>
    <?php if (isAdmin()): ?>
    <li>
      <a href="attributes.php" class="nav-link <?= $current_page === 'attributes' ? 'active' : '' ?>">
        Product Attributes
      </a>
    </li>
    <?php endif; ?>
    <?php if (isAdmin()): ?>
    <li>
      <a href="audit_log.php" class="nav-link <?= $current_page === 'audit_log' ? 'active' : '' ?>">
        Audit Log
      </a>
    </li>
    <li>
      <a href="users.php" class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>">
        Manage Users
      </a>
    </li>
    <?php endif; ?>
  </ul>

  <div class="sidebar-bottom">
    <a href="profile.php" class="sidebar-user" title="My Profile">
      <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="user-role"><?= ucfirst($user['role']) ?></div>
      </div>
    </a>
    <div class="sidebar-divider"></div>
    <a href="logout.php" class="logout-btn" title="Logout" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
  </div>
</nav>

<main class="main-content">
  <div id="sidebarOverlay" class="sidebar-overlay"></div>
  <div class="page-header">
    <div class="header-left">
      <button id="mobileToggle" class="mobile-toggle">☰</button>
      <h2 class="page-title"><?= $page_title ?? 'Dashboard' ?></h2>
    </div>
    <div class="page-meta"><?= date('l, d F Y') ?></div>
  </div>
