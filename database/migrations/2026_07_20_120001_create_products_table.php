<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Minimal placeholder so Category::products() / the "block delete while
   * products exist" rule can be tested now. This table will gain its real
   * columns (name, slug, price, stock, status, ...) in the Products point.
   */
  public function up(): void
  {
    Schema::create('products', function (Blueprint $table) {
      $table->id();
      $table->foreignId('category_id')->constrained()->cascadeOnDelete();
      $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('products');
  }
};
