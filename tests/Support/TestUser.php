<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use AndyDefer\Mfa\Otp\Contracts\MustOtpChannels;
use AndyDefer\Mfa\Otp\Traits\HasOneTimePasswords;
use AndyDefer\Mfa\Totp\Traits\HasTwoFactorAuthentication;

/**
 * Test model representing a user entity for OTP testing purposes.
 *
 * This model serves as a test double for real application User models,
 * implementing the required OTP channel configuration interface and
 * using the HasOneTimePasswords trait. It's used exclusively in the
 * test suite to verify OTP functionality.
 */
final class TestUser extends Model implements MustOtpChannels
{
    use HasOneTimePasswords;
    use HasTwoFactorAuthentication;
    use Notifiable;
    use SoftDeletes;

    /**
     * The database table associated with the model.
     */
    protected $table = 'test_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the OTP delivery channels for this user.
     *
     * @return array<int, string>
     */
    public function getOtpChannels(): array
    {
        return ['mail'];
    }
}
