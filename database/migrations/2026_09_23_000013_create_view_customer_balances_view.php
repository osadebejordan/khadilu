<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS view_customer_balances");
        DB::statement("
            CREATE VIEW view_customer_balances AS
            SELECT 
                c.*,
                (
                    (SELECT IFNULL(SUM(total_amount - amount_paid), 0) FROM stock_outflow WHERE customer_id = c.id) -
                    (SELECT IFNULL(SUM(amount), 0) FROM customer_payments WHERE customer_id = c.id) -
                    (SELECT IFNULL(SUM(refund_amount), 0) FROM stock_returns WHERE customer_id = c.id AND status = 'Approved')
                ) AS balance
            FROM customers c;
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS view_customer_balances");
    }
};
