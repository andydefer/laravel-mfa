<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the test_users table for package testing.
 *
 * This table is used exclusively by the test suite to verify OTP
 * functionality with a realistic Eloquent model. It includes standard
 * user fields and soft deletes support.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     *
     * Creates the test_users table with standard user authentication fields,
     * timestamps, and soft delete support.
     */
    public function up(): void
    {
        Schema::create('test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migration.
     *
     * Drops the test_users table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_users');
    }
};
