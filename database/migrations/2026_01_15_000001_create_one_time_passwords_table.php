<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_time_passwords', function (Blueprint $table): void {
            // ✅ UUID comme clé primaire
            $table->uuid('id')->primary();

            // Polymorphic relationship columns (otpable_type, otpable_id)
            $table->morphs('otpable');

            // Hashed token for security (never store raw tokens)
            $table->string('token_hash', 64);

            // OTP type: 'email_verification', 'password_reset', '2fa', etc.
            $table->string('type', 50);

            // Destination address: email, phone number, etc.
            $table->string('destination', 255);

            // JSON array of delivery channels used (sms, email, whatsapp, etc.)
            $table->json('channels')->nullable();

            // Additional metadata (IP address, user agent, etc.)
            $table->json('meta')->nullable();

            // Attempt tracking
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);

            // Status timestamps
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Performance indexes for common queries
            $table->index('token_hash');
            $table->index('expires_at');
            $table->index('type');
            $table->index('destination');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
