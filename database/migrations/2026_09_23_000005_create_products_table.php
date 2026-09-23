<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('brand', 80)->nullable();
            $table->text('compatible_models')->nullable();
            $table->string('category', 60)->default('Smartphone');
            $table->string('quality', 50)->default('Original');
            $table->string('sku', 60)->unique()->nullable();
            $table->integer('current_stock')->default(0);
            $table->decimal('selling_price', 12, 2)->default(0.00);
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->integer('low_stock_threshold')->default(3);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
