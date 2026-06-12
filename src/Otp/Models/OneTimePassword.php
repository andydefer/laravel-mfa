<?php
// src/Otp/Models/OneTimePassword.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class OneTimePassword extends Model
{
    protected $table = 'one_time_passwords';

    // ✅ Désactiver l'auto-incrément
    public $incrementing = false;

    // ✅ Définir le type de clé primaire
    protected $keyType = 'string';

    protected $fillable = [
        'id',
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
        'created_at',
        'updated_at',
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
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function otpable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired()
            && !$this->isVerified()
            && !$this->isUsed()
            && !$this->isCancelled();
    }
}
