<?php

declare(strict_types=1);

// database/migrations/2026_01_15_000001_create_one_time_passwords_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the one-time passwords table.
 *
 * This table stores OTP tokens for various authentication operations,
 * with support for polymorphic relationships, multiple channels,
 * and comprehensive lifecycle tracking (verification, usage, cancellation).
 */
return new class extends Migration
{
    /**
     * Run the migration.
     *
     * Creates the 'one_time_passwords' table with all necessary columns
     * and indexes for efficient OTP management.
     */
    public function up(): void
    {
        Schema::create('one_time_passwords', function (Blueprint $table): void {
            // Primary identifier
            $table->id();

            // Polymorphic relationship (owner of the OTP: User, Admin, etc.)
            $table->morphs('otpable');

            // Core OTP data
            $table->string('token_hash', 64);
            $table->string('type', 50);
            $table->string('destination', 255);

            // Delivery and metadata
            $table->json('channels')->nullable();
            $table->json('meta')->nullable();

            // Security and attempt tracking
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);

            // Lifecycle timestamps
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // System timestamps
            $table->timestamps();

            // Performance indexes
            $table->index('token_hash');
            $table->index('expires_at');
            $table->index('type');
            $table->index('destination');
        });
    }

    /**
     * Reverse the migration.
     *
     * Drops the 'one_time_passwords' table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
