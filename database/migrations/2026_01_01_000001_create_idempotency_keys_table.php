<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('endpoint');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_hash');
            $table->string('status')->default('in_progress'); // in_progress | completed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Same key can be reused across different endpoints/users without colliding,
            // but must be unique for a given (key, endpoint, user) triple.
            $table->unique(['key', 'endpoint', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
