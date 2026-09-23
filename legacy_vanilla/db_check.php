<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

if (!isAdmin()) {
    die("Access Denied: Only administrators can run diagnostics.");
}

$page_title = "Database Health Check";
require_once 'includes/header.php';

// --- Handle Applying Fixes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_fix'])) {
    $sql_to_run = $_POST['sql_to_run'] ?? '';
    if ($sql_to_run) {
        try {
            $conn->multi_query($sql_to_run);
            while ($conn->more_results() && $conn->next_result()) {;
            } // Flush multi-results
            echo "<div class='alert alert-success'>✅ Database fixes applied successfully!</div>";
        } catch (Exception $e) {
            log_error($e, 'DB_FIX_APPLY');
            echo "<div class='alert alert-error'>❌ Failed to apply fixes: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Define the "Gold Standard" columns that must exist for the code to work
$required_schema = [
    'users' => ['role', 'permissions'],
    'customers' => ['type'],
    'products' => ['brand', 'compatible_models', 'quality', 'low_stock_threshold'],
    'stock_inflow' => ['user_id', 'supplier_phone'],
    'sales_transactions' => ['id', 'customer_id', 'total_cart_value', 'total_paid'],
    'stock_outflow' => ['transaction_id', 'user_id', 'customer_id', 'amount_paid', 'cost_price'],
    'stock_returns' => ['id', 'product_id', 'customer_id', 'outflow_id', 'refund_amount', 'status'],
    'repairs' => ['id', 'customer_id', 'product_id', 'device_model', 'estimated_cost', 'amount_paid', 'status'],
    'customer_payments' => ['id', 'customer_id', 'user_id', 'amount', 'payment_method'],
    'system_audit_log' => ['id', 'user_id', 'action', 'details'],
    'categories' => ['id', 'name'],
    'qualities' => ['id', 'name', 'category_name'],
    'business_expenses' => ['id', 'user_id', 'category', 'amount', 'description', 'expense_date']
];

echo "<div class='card' style='padding:20px;'>";
echo "<h3>🔍 Running System Diagnostics...</h3><br>";

$missing_anything = false;
$fix_sql = "";

foreach ($required_schema as $table => $columns) {
    echo "<div style='margin-bottom:15px;'><strong>Table: $table</strong><br>";

    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check->num_rows == 0) {
        echo "<span class='red-text'>❌ Table '$table' is missing!</span><br>";
        $missing_anything = true;

        $fix_sql .= match ($table) {
            'repairs' => "CREATE TABLE repairs (
                id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT DEFAULT NULL, product_id INT DEFAULT NULL,
                customer_name VARCHAR(150) NOT NULL, customer_phone VARCHAR(30) DEFAULT NULL, device_model VARCHAR(150) NOT NULL,
                category VARCHAR(100) DEFAULT 'Repair', issue_description TEXT NOT NULL, estimated_cost DECIMAL(12,2) DEFAULT 0.00,
                amount_paid DECIMAL(12,2) DEFAULT 0.00, status ENUM('Received', 'In-Progress', 'Ready', 'Delivered', 'Cancelled') DEFAULT 'Received',
                notes TEXT DEFAULT NULL, user_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL); ",
            'stock_returns' => "CREATE TABLE stock_returns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                customer_id INT DEFAULT NULL, outflow_id INT DEFAULT NULL, user_id INT NOT NULL,
                quantity INT NOT NULL, refund_amount DECIMAL(12,2) DEFAULT 0.00, notes TEXT,
                status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT); ",
            'sales_transactions' => "CREATE TABLE sales_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT DEFAULT NULL, customer_name VARCHAR(120) NOT NULL,
                user_id INT NOT NULL, total_cart_value DECIMAL(12,2) NOT NULL, total_paid DECIMAL(12,2) NOT NULL,
                payment_method ENUM('Cash','Transfer','POS','Other') DEFAULT 'Cash', notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL); ",
            'system_audit_log' => "CREATE TABLE system_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, action VARCHAR(50) NOT NULL,
                table_name VARCHAR(50), record_id INT, details TEXT, ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE); ",
            'customer_payments' => "CREATE TABLE customer_payments (
                id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL, user_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL, payment_method ENUM('Cash', 'POS', 'Transfer', 'Other') DEFAULT 'Cash',
                notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE); ",
            'business_expenses' => "CREATE TABLE business_expenses (
                id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, 
                category ENUM('Rent', 'Electricity', 'Salary', 'Marketing', 'Transport', 'Others') DEFAULT 'Others',
                amount DECIMAL(12,2) NOT NULL, description TEXT, expense_date DATE DEFAULT (CURRENT_DATE),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP); ",
            default => ""
        };
        continue;
    }

    // Check for specific columns
    foreach ($columns as $col) {
        $col_check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($col_check->num_rows == 0) {
            echo "<span class='red-text'>❌ Missing Column: '$col'</span><br>";

            // Generate the fix
            $fix_sql .= "ALTER TABLE `$table` ADD COLUMN `$col` ";
            if ($col == 'role') $fix_sql .= "ENUM('admin', 'staff', 'sub-admin') DEFAULT 'staff' AFTER password; ";
            elseif ($col == 'permissions') $fix_sql .= "TEXT DEFAULT NULL AFTER role; ";
            elseif ($col == 'user_id') $fix_sql .= "INT NOT NULL AFTER product_id; ";
            elseif ($col == 'amount_paid') $fix_sql .= "DECIMAL(12,2) DEFAULT 0.00 AFTER total_amount; ";
            elseif ($col == 'customer_id') $fix_sql .= "INT DEFAULT NULL AFTER user_id; ";
            elseif ($col == 'brand') $fix_sql .= "VARCHAR(80) AFTER name; ";
            elseif ($col == 'compatible_models') $fix_sql .= "TEXT AFTER brand; ";
            elseif ($col == 'quality') $fix_sql .= "VARCHAR(50) DEFAULT 'Original' AFTER category; ";
            elseif ($col == 'outflow_id' && $table == 'stock_returns') $fix_sql .= "INT DEFAULT NULL AFTER customer_id; ";
            elseif ($col == 'status' && $table == 'stock_returns') $fix_sql .= "ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending' AFTER notes; ";
            elseif ($col == 'category_name' && $table == 'qualities') $fix_sql .= "VARCHAR(100) DEFAULT NULL AFTER name; ";
            elseif ($col == 'cost_price' && $table == 'stock_outflow') $fix_sql .= "DECIMAL(12,2) DEFAULT 0.00 AFTER quantity; ";
            elseif ($col == 'product_id') $fix_sql .= "INT DEFAULT NULL AFTER id; ";
            elseif ($col == 'customer_phone') $fix_sql .= "VARCHAR(20) AFTER customer_name; ";
            elseif ($col == 'low_stock_threshold') $fix_sql .= "INT DEFAULT 3 AFTER cost_price; ";
            elseif ($col == 'type') $fix_sql .= "ENUM('Retail') DEFAULT 'Retail' AFTER phone; ";
            elseif ($col == 'supplier_phone') $fix_sql .= "VARCHAR(20) AFTER supplier; ";

            // For system_audit_log
            elseif ($col == 'action') $fix_sql .= "VARCHAR(50) NOT NULL AFTER user_id; ";
            elseif ($col == 'details') $fix_sql .= "TEXT AFTER record_id; ";
            elseif ($col == 'record_id') $fix_sql .= "INT AFTER table_name; ";

            // For stock_outflow
            elseif ($col == 'transaction_id') $fix_sql .= "INT DEFAULT NULL AFTER id; ALTER TABLE `$table` ADD CONSTRAINT fk_transaction_id FOREIGN KEY (transaction_id) REFERENCES sales_transactions(id) ON DELETE CASCADE; ";
            $missing_anything = true;
        } else {
            echo "<span class='green-text'>✓ Column '$col' is OK</span><br>";
        }
    }
    echo "</div>";
}

// --- Specific Check for Sub-Admin Role ENUM ---
$role_info = $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch_assoc();
if ($role_info && !str_contains($role_info['Type'], 'sub-admin')) {
    echo "<div style='margin-bottom:15px;'><span class='red-text'>❌ Role ENUM is outdated (missing 'sub-admin')</span><br></div>";
    $missing_anything = true;
    $fix_sql .= "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'staff', 'sub-admin') DEFAULT 'staff'; ";
}

// --- Check for the View ---
echo "<div style='margin-bottom:15px;'><strong>System View: view_customer_balances</strong><br>";
$view_check = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . DB_NAME . " = 'view_customer_balances'");
if ($view_check->num_rows == 0) {
    echo "<span class='red-text'>❌ View 'view_customer_balances' is missing!</span><br>";
    $missing_anything = true;
    $fix_sql .= "CREATE OR REPLACE VIEW view_customer_balances AS
    SELECT 
        c.*,
        (
            (SELECT IFNULL(SUM(total_amount - amount_paid), 0) FROM stock_outflow WHERE customer_id = c.id) + 
            (SELECT IFNULL(SUM(amount), 0) * -1 FROM customer_payments WHERE customer_id = c.id) -
            (SELECT IFNULL(SUM(refund_amount), 0) FROM stock_returns WHERE customer_id = c.id AND status = 'Approved')
        ) AS balance
    FROM customers c; ";
} else {
    echo "<span class='green-text'>✓ View is OK</span><br>";
}

if ($missing_anything) {
    echo "<div class='alert alert-error' style='margin-top:20px;'>";
    echo "<strong>Mismatch Detected!</strong><br>Your database is behind your code. Run the SQL below in phpMyAdmin to fix it:";
    echo "<pre style='background:#000; padding:15px; margin-top:10px; color:#00d4aa; overflow-x:auto;'>$fix_sql</pre>";
    echo "<form method='POST' style='margin-top:15px;'>";
    echo "<input type='hidden' name='sql_to_run' value='" . htmlspecialchars($fix_sql) . "'>";
    echo "<button type='submit' name='apply_fix' class='btn btn-primary'>Apply Fixes Automatically</button>";
    echo "</form>";
    echo "</div>";
} else {
    echo "<div class='alert alert-success'>🎉 Database is 100% in sync with your code!</div>";
}

echo "</div>";
require_once 'includes/footer.php';
