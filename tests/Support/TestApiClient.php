<?php

declare(strict_types=1);

namespace Kani\Otp\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kani\Otp\Contracts\MustNemesis;
use Kani\Otp\Traits\HasNemesisTokens;

/**
 * Test model for API clients that can authenticate with tokens.
 *
 * This model is used exclusively for testing the Nemesis package.
 * It implements the MustNemesis contract and uses the HasNemesisTokens trait
 * to provide full token management capabilities for API client testing.
 *
 * @package Kani\Otp\Tests\Support
 */
final class TestApiClient extends Model implements MustNemesis
{
    use HasNemesisTokens;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'test_api_clients';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'api_key',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'api_key',
    ];

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
            'type' => 'api_client',
        ];
    }
}
