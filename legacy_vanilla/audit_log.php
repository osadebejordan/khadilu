<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'System Audit Log';
require_once 'includes/header.php';

$logs = $conn->query("
    SELECT l.*, u.full_name, u.username 
    FROM system_audit_log l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <h3>Administrative Activity <span class="dim small">(Last 100 actions)</span></h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="mono small"><?= date('d M Y, H:i:s', strtotime($l['created_at'])) ?></td>
                    <td>
                        <div class="bold"><?= htmlspecialchars($l['full_name']) ?></div>
                        <div class="dim small">@<?= htmlspecialchars($l['username']) ?></div>
                    </td>
                    <td><span class="pill"><?= htmlspecialchars($l['action']) ?></span></td>
                    <td>
                        <?php if ($l['table_name']): ?>
                            <span class="dim small"><?= strtoupper($l['table_name']) ?></span> 
                            <span class="mono">#<?= $l['record_id'] ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars($l['details'] ?? '—') ?></td>
                    <td class="dim mono small"><?= $l['ip_address'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="empty-state">No activity recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>