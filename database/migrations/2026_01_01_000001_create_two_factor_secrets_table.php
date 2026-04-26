<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for the two-factor authentication secrets table.
 *
 * This table stores TOTP secrets for two-factor authentication,
 * supporting polymorphic relationships with any authenticatable model
 * (User, Admin, etc.) and includes recovery codes for account recovery.
 */
return new class extends Migration
{
    /**
     * Execute the migration and create the two_factor_secrets table.
     */
    public function up(): void
    {
        Schema::create('two_factor_secrets', function (Blueprint $table): void {
            $table->id();

            // Polymorphic relationship supports any authenticatable model
            $table->morphs('authenticatable');

            // TOTP shared secret in Base32 format for code generation
            $table->string('secret', 64);

            // QR code metadata for authenticator app configuration
            $table->string('issuer')->nullable();   // Application name (e.g., "MyApp")
            $table->string('label')->nullable();    // User identifier (e.g., email)

            // Hashed recovery codes for account recovery when 2FA is inaccessible
            $table->json('recovery_codes')->nullable();

            // Additional context (IP address, user agent, device information)
            $table->json('meta')->nullable();

            // Status flags
            $table->boolean('is_enabled')->default(false);      // Active 2FA status
            $table->timestamp('confirmed_at')->nullable();      // 2FA confirmation timestamp
            $table->timestamp('last_used_at')->nullable();      // Last successful verification

            $table->timestamps();

            // Performance indexes for polymorphic and status queries
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migration and drop the two_factor_secrets table.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_secrets');
    }
};
