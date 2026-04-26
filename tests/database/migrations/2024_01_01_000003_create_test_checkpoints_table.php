<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the test_checkpoints table for package testing.
 *
 * This table is used exclusively by the test suite to verify checkpoint/turnstile
 * authentication functionality. It represents a physical or logical checkpoint
 * that requires OTP verification.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     *
     * Creates the test_checkpoints table with basic checkpoint fields
     * including location tracking, active status, and soft delete support.
     */
    public function up(): void
    {
        Schema::create('test_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migration.
     *
     * Drops the test_checkpoints table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_checkpoints');
    }
};
