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
 * Only the hashed token is stored; plain codes are never persisted.
 *
 * @property int $id
 * @property string $otpable_type
 * @property int $otpable_id
 * @property string $token_hash
 * @property string $type
 * @property string $destination
 * @property array|null $channels
 * @property array|null $meta
 * @property int $attempts
 * @property int $max_attempts
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class OneTimePassword extends Model
{
    protected $table = 'one_time_passwords';

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
     * Determine if the OTP has expired.
     *
     * @return bool True if the expiration timestamp is in the past
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine if the OTP has been verified.
     *
     * @return bool True if the verified_at timestamp is not null
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Determine if the OTP has been used.
     *
     * @return bool True if the used_at timestamp is not null
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Determine if the OTP has been cancelled.
     *
     * @return bool True if the cancelled_at timestamp is not null
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Determine if the OTP is currently valid for verification.
     *
     * An OTP is valid when it is NOT expired, verified, used, or cancelled.
     *
     * @return bool True if the OTP can be verified
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
     *
     * @return bool True if attempts >= max_attempts
     */
    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Verify a plain text code against the stored hash.
     *
     * @param string $plainCode The plain text code provided by the user
     * @return bool True if the code matches the stored hash
     */
    public function verifyCode(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->token_hash);
    }

    /**
     * Mark the OTP as verified by setting the verification timestamp.
     *
     * @return self Returns the model instance for method chaining
     */
    public function markAsVerified(): self
    {
        $this->verified_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark the OTP as used by setting the usage timestamp.
     *
     * An OTP can only be used once. This should be called after successful
     * verification and execution of the intended action.
     *
     * @return self Returns the model instance for method chaining
     */
    public function markAsUsed(): self
    {
        $this->used_at = now();
        $this->save();

        return $this;
    }

    /**
     * Mark the OTP as cancelled by setting the cancellation timestamp.
     *
     * This is useful when an OTP is invalidated before verification
     * (e.g., user requests a new OTP, or an admin revokes it).
     *
     * @return self Returns the model instance for method chaining
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
     * @return self Returns the model instance for method chaining
     */
    public function incrementAttempts(): self
    {
        $this->attempts++;
        $this->save();

        return $this;
    }
}
