<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens for the Driver mobile app (VMIS Driver). A deliberately
 * small, self-contained auth mechanism rather than pulling in Laravel
 * Sanctum: the mobile app only ever needs a single long-lived bearer token
 * per device, never SPA cookie/CSRF auth, so this keeps the dependency
 * list — and the install steps — unchanged.
 *
 * Only the SHA-256 hash of each token is stored, never the plain value
 * (same principle as the users.password column) — see
 * App\Models\ApiToken::generate().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
