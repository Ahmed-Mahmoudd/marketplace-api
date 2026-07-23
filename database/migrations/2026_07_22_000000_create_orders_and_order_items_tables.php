<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('orders', function (Blueprint $table) {
      $table->id();
      $table->string('order_number')->unique();
      $table->foreignId('user_id')->constrained()->cascadeOnDelete();
      $table->string('status')->default('pending');
      $table->string('payment_method')->default('cod');
      $table->string('payment_status')->default('pending');
      $table->decimal('subtotal', 10, 2);
      $table->decimal('total', 10, 2);
      $table->timestamps();

      $table->index('status');
    });

    Schema::create('order_items', function (Blueprint $table) {
      $table->id();
      $table->foreignId('order_id')->constrained()->cascadeOnDelete();
      $table->foreignId('product_id')->constrained()->restrictOnDelete();
      $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
      $table->string('product_name');
      $table->decimal('unit_price', 10, 2);
      $table->unsignedInteger('quantity');
      $table->decimal('subtotal', 10, 2);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('order_items');
    Schema::dropIfExists('orders');
  }
};
