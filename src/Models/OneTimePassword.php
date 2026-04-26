<?php

declare(strict_types=1);

namespace Kani\Otp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Hash;

/**
 * Eloquent model for one-time password (OTP) records.
 *
 * This model represents an OTP token in the database, handling the complete
 * lifecycle of an OTP: creation, verification, expiration, usage, and cancellation.
 * It supports polymorphic relationships to any model (User, Admin, etc.)
 * and stores the token as a secure hash.
 */
class OneTimePassword extends Model
{
    /**
     * The database table associated with the model.
     */
    protected $table = 'one_time_passwords';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'otpable_type',
        'otpable_id',
        'token_hash',
        'type',
        'destination',
        'channels',
        'meta',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'used_at',
        'cancelled_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'channels' => 'array',
        'meta' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'immutable_datetime',
        'verified_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    /**
     * Get the parent otpable model (polymorphic relationship).
     *
     * @return MorphTo
     */
    public function otpable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if the OTP has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the OTP has been verified (code matched but not yet used).
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if the OTP has been used (consumed after verification).
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Check if the OTP has been cancelled (invalidated before use).
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Check if the OTP is still valid (not expired, verified, used, or cancelled).
     *
     * A valid OTP can still be verified and used.
     */
    public function isValid(): bool
    {
        return !$this->isExpired()
            && !$this->isVerified()
            && !$this->isUsed()
            && !$this->isCancelled();
    }

    /**
     * Check if the maximum number of verification attempts has been exceeded.
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Verify a plaintext code against the stored hash.
     *
     * @param string $plainCode The plaintext OTP code to verify
     */
    public function verifyCode(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->token_hash);
    }

    /**
     * Mark the OTP as verified (code successfully matched).
     *
     * @return $this
     */
    public function markAsVerified(): self
    {
        $this->verified_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark the OTP as used (consumed after verification).
     *
     * @return $this
     */
    public function markAsUsed(): self
    {
        $this->used_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark the OTP as cancelled (invalidated before use).
     *
     * @return $this
     */
    public function markAsCancelled(): self
    {
        $this->cancelled_at = now();
        $this->save();

        return $this;
    }

    /**
     * Increment the verification attempts counter.
     *
     * @return $this
     */
    public function incrementAttempts(): self
    {
        $this->attempts++;
        $this->save();

        return $this;
    }
}
