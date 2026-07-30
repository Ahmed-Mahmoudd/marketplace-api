<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Short-lived, single-use codes for handing a signed-in vendor from the
   * storefront to the analytics dashboard without a second login.
   *
   * Only a hash of the code is stored: a leaked database row must not be
   * redeemable, the same reason password reset tokens are hashed.
   */
  public function up(): void
  {
    Schema::create('handoff_codes', function (Blueprint $table) {
      $table->id();
      $table->string('code_hash', 64)->unique();
      $table->foreignId('user_id')->constrained()->cascadeOnDelete();
      $table->timestamp('expires_at');
      $table->timestamp('redeemed_at')->nullable();
      $table->timestamps();

      $table->index('expires_at');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('handoff_codes');
  }
};
