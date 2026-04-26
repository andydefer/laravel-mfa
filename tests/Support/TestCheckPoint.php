<?php

declare(strict_types=1);

namespace Kani\Otp\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kani\Otp\Contracts\MustNemesis;
use Kani\Otp\Traits\HasNemesisTokens;

/**
 * Test model for checkpoints (billeterie) that can authenticate with tokens.
 *
 * This model represents a physical checkpoint (turnstile, gate, etc.)
 * that needs to authenticate with Nemesis tokens for ticket validation.
 *
 * @package Kani\Otp\Tests\Support
 */
final class TestCheckPoint extends Model implements MustNemesis
{
    use HasNemesisTokens;
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
     * Define the format for authenticated API responses.
     *
     * @return array<string, mixed>
     */
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'status' => $this->is_active ? 'active' : 'inactive',
            'last_seen' => $this->last_ping_at?->toIso8601String(),
            'type' => 'checkpoint',
        ];
    }
}
