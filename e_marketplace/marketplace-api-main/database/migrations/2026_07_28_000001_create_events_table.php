<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Behavioural event stream (product view -> cart add -> purchase).
   *
   * Denormalised `vendor_id` is copied from the product at write time so the
   * analytics endpoints can scope a whole funnel to one vendor without joining
   * through products, and so the row survives the product being reassigned.
   */
  public function up(): void
  {
    Schema::create('events', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
      $table->foreignId('product_id')->constrained()->cascadeOnDelete();
      $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
      $table->string('type'); // view | cart | purchase
      $table->string('session_id')->nullable(); // groups guest activity
      $table->timestamps();

      $table->index(['vendor_id', 'type']);
      $table->index(['vendor_id', 'created_at']);
      $table->index(['product_id', 'type']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('events');
  }
};
