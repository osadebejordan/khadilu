<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Security: Only admins should run system-level scripts
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Inventory Intelligence';
$output = '';
$exit_code = 0;
$report_file = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_analysis'])) {
    // Ensure Python 3 is installed and the mysql-connector-python package is available
    $script_path = escapeshellarg(__DIR__ . "/stock_analyzer.py");

    // Check for a local virtual environment first, otherwise fallback to system python3
    $venv_path = __DIR__ . "/venv/bin/python3";
    $python_bin = file_exists($venv_path) ? $venv_path : "python3";

    // Using exec instead of shell_exec to capture the exit status
    $full_output = [];
    $command = "$python_bin $script_path 2>&1";
    exec($command, $full_output, $exit_code);
    $output = implode("\n", $full_output);

    if ($exit_code === 0) {
        // Extract the filename from the Python output using a simple pattern match
        if (preg_match('/Detailed report saved as: (dead_stock_report_.*?\.csv)/', $output, $matches)) {
            $report_file = $matches[1];
        }
        logActivity('RUN_STOCK_ANALYSIS', 'products', null, "Python analytics engine executed successfully.");
    } else {
        log_error(new Exception("Python Engine Failed (Code $exit_code): $output"), 'PYTHON_SYSTEM');
    }
}

require_once 'includes/header.php';
echo renderToast($exit_code !== 0 && $output ? "Analytics engine encountered an error." : "", "error");
?>

<div class="card">
    <div class="card-header">
        <h3>📊 Advanced Stock Analytics</h3>
    </div>
    <div class="card-body" style="padding: 30px;">
        <div class="empty-state" style="max-width: 600px; margin: 0 auto 30px auto; border: 1px dashed var(--border);">
            <p>This engine analyzes sales velocity and identifies <strong>"Dead Stock"</strong>—items that have not moved in over 30 days. It calculates frozen capital to help you optimize cash flow.</p>
            <form method="POST">
                <button type="submit" name="run_analysis" class="btn btn-primary btn-lg">Execute Analysis Engine</button>
            </form>
        </div>

        <?php if ($output !== ''): ?>
            <div style="background: var(--surface-2); padding: 20px; border-radius: 8px; border: 1px solid <?= $exit_code === 0 ? 'var(--border-l)' : 'var(--red)' ?>;">
                <h4 style="margin-top:0; color: <?= $exit_code === 0 ? 'var(--accent)' : 'var(--red)' ?>;">
                    <?= $exit_code === 0 ? '✅ Engine Output:' : '❌ Engine Error (Code ' . $exit_code . '):' ?>
                </h4>
                <pre style="white-space: pre-wrap; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; line-height: 1.6; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 4px;"><?= htmlspecialchars($output) ?></pre>

                <?php if ($exit_code !== 0 && strpos($output, 'ModuleNotFoundError') !== false): ?>
                    <div class="alert alert-error" style="margin-top: 15px; font-size: 0.8rem;">
                        <strong>Troubleshooting:</strong> The Python <code>mysql-connector-python</code> library is missing. <br>
                        Run: <code>sudo python3 -m pip install mysql-connector-python</code> in your server terminal.
                    </div>
                <?php endif; ?>

                <?php if ($report_file && file_exists(__DIR__ . '/' . $report_file)): ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                        <a href="<?= htmlspecialchars($report_file) ?>" class="btn btn-success" download>📥 Download Detailed CSV Report</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>