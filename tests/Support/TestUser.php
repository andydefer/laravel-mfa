<?php

declare(strict_types=1);

namespace Kani\Otp\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kani\Otp\Contracts\MustNemesis;
use Kani\Otp\Traits\HasNemesisTokens;

/**
 * Test model for users that can authenticate with Nemesis tokens.
 *
 * This model represents a typical User model in a Laravel application
 * and demonstrates the correct implementation of the MustNemesis interface.
 * Used for testing token authentication in a realistic context.
 *
 * @package Kani\Otp\Tests\Support
 */
final class TestUser extends Model implements MustNemesis
{
    use HasNemesisTokens;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
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
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Define the format for authenticated API responses.
     *
     * @return array<string, mixed>
     */
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'type' => 'user',
        ];
    }
}
