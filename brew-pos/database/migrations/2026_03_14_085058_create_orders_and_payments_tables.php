<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->enum('status', ['pending', 'preparing', 'ready', 'completed', 'cancelled'])->default('pending');
            $table->enum('order_type', ['dine_in', 'takeout', 'delivery'])->default('dine_in');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('customer_name', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 200);
            $table->decimal('product_price', 10, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('subtotal', 10, 2);
            $table->text('notes')->nullable();
            $table->json('addons')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'gcash'])->default('cash');
            $table->decimal('amount_tendered', 10, 2);
            $table->decimal('change_amount', 10, 2)->default(0);
            $table->string('reference_number', 100)->nullable();
            $table->enum('status', ['pending', 'completed', 'refunded'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('low_stock_threshold')->default(10);
            $table->string('unit', 20)->default('pcs');
            $table->timestamps();
        });

        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['sale', 'restock', 'adjustment', 'waste'])->default('sale');
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
