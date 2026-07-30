<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('products', function (Blueprint $table) {
      $table->string('name')->after('vendor_id');
      $table->string('slug')->unique()->after('name');
      $table->text('description')->nullable()->after('slug');
      $table->decimal('price', 10, 2)->after('description');
      $table->unsignedInteger('stock')->default(0)->after('price');
      $table->string('status')->default('active')->after('stock');
    });
  }

  public function down(): void
  {
    Schema::table('products', function (Blueprint $table) {
      $table->dropColumn(['name', 'slug', 'description', 'price', 'stock', 'status']);
    });
  }
};
