<?php

declare(strict_types=1);

namespace Kani\Otp\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kani\Otp\Contracts\MustNemesis;
use Kani\Otp\Traits\HasNemesisTokens;

/**
 * Test model for users with custom nemesisFormat implementation.
 *
 * This model demonstrates a custom format for API responses,
 * different from the standard TestUser format.
 *
 * @package Kani\Otp\Tests\Support
 */
final class TestCustomFormatUser extends Model implements MustNemesis
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
        'password',
        'remember_token',
        'email_verified_at',  // ← Ajouter ce champ
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',  // ← S'assurer du cast
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
     * Define custom format for authenticated API responses.
     * This format excludes email and adds custom fields.
     *
     * @return array<string, mixed>
     */
    public function nemesisFormat(): array
    {
        return [
            'user_id' => $this->id,
            'full_name' => $this->name,
            'is_verified' => !is_null($this->email_verified_at),
            'custom_field' => 'only_for_api',
            'type' => 'custom_user',
        ];
    }
}
