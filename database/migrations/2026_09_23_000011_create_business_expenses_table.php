<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('category', ['Rent', 'Electricity', 'Salary', 'Marketing', 'Transport', 'Others'])->default('Others');
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->date('expense_date')->default(now()->toDateString());
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_expenses');
    }
};
