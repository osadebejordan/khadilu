# 📱 Khadilu Phone Hub — Inventory Management System

A clean, full-featured inventory management system for Khadilu Phone Hub,
built with PHP + MySQL. Tracks stock inflow, sales, prices, and generates reports.

---

## ⚡ Quick Setup (XAMPP)

### Step 1 — Copy files
Put the entire `khadilu/` folder inside your XAMPP `htdocs/` directory.
```
C:/xampp/htdocs/khadilu/     (Windows)
/opt/lampp/htdocs/khadilu/   (Linux)
```

### Step 2 — Create the database
1. Open your browser → `http://localhost/phpmyadmin`
2. Click **SQL** tab
3. Paste the contents of `setup.sql` and click **Go**

### Step 3 — Configure database (if needed)
Open `config.php` and update if your MySQL has a password:
```php
define('DB_PASS', 'your_mysql_password');
```

### Step 4 — Create admin account
Go to: `http://localhost/khadilu/install.php`
Fill in your name, username, and password.
**Delete `install.php` after setup for security.**

### Step 5 — Login
Go to: `http://localhost/khadilu/`
Sign in with the credentials you just created.

---

## Features

| Feature | Description |
|---|---|
| 📊 Dashboard | Live stats: today's revenue, monthly revenue, inventory value, low stock alerts |
| 📦 Inventory | Add/edit/delete products with cost price, selling price, SKU, category |
| ⬇ Add Stock | Record stock inflow from suppliers, auto-updates inventory |
| ⬆ Record Sale | Log sales, auto-fills selling price, checks stock availability |
| 📋 History | Filter transactions by date range, type (sale/inflow), product; shows profit |
| 🔐 Auth | Secure login with bcrypt password hashing |

---

## Tips
- The **margin column** in Inventory shows profit per unit and percentage
- **Low stock badge** on the sidebar shows number of products running low
- History page shows **gross profit** for any selected date range
- Click **Restock** in the Dashboard alerts to quickly add stock for that product

---

## Tech Stack
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB
- **Frontend**: Vanilla HTML/CSS/JS (no framework needed)
- **Fonts**: Syne, Nunito Sans, JetBrains Mono (Google Fonts)

Built for Kadilu Phone Hub.
