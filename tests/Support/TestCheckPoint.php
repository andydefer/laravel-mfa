<?php

declare(strict_types=1);

namespace Kani\Mfa\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kani\Mfa\Otp\Contracts\MustOtpChannels;
use Kani\Mfa\Otp\Traits\HasOneTimePasswords;

/**
 * Test model for checkpoints (billeterie) that can authenticate with tokens.
 *
 * This model represents a physical checkpoint (turnstile, gate, etc.)
 * that needs to authenticate with Nemesis tokens for ticket validation.
 *
 * @package Kani\Mfa\Tests\Support
 */
final class TestCheckPoint extends Model implements MustOtpChannels
{
    use HasOneTimePasswords;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'test_checkpoints';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'location',
        'is_active',
        'last_ping_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_ping_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
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
