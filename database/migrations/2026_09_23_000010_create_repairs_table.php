<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30)->nullable();
            $table->string('device_model', 150);
            $table->string('category', 100)->default('Repair');
            $table->text('issue_description');
            $table->decimal('estimated_cost', 12, 2)->default(0.00);
            $table->decimal('amount_paid', 12, 2)->default(0.00);
            $table->enum('status', ['Received', 'In-Progress', 'Ready', 'Delivered', 'Cancelled'])->default('Received');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
