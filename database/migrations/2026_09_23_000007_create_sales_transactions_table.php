<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('customer_name', 120);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_cart_value', 12, 2);
            $table->decimal('total_paid', 12, 2);
            $table->enum('payment_method', ['Cash', 'Transfer', 'POS', 'Other'])->default('Cash');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transactions');
    }
};
