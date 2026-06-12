<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Totp\Models;

use AndyDefer\Mfa\Totp\Services\TOTPService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent model for TOTP (Time-based One-Time Password) secrets.
 *
 * This model represents a two-factor authentication configuration for any
 * authenticatable model (User, Doctor, Admin, etc.). It stores the shared
 * secret used by Google Authenticator to generate codes.
 *
 * @property int $id
 * @property string $authenticatable_type
 * @property int $authenticatable_id
 * @property string $secret
 * @property string|null $issuer
 * @property string|null $label
 * @property array|null $recovery_codes
 * @property array|null $meta
 * @property bool $is_enabled
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TwoFactorSecret extends Model
{
    /**
     * The database table associated with the model.
     *
     * @var string
     */
    protected $table = 'two_factor_secrets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'secret',
        'issuer',
        'label',
        'recovery_codes',
        'meta',
        'is_enabled',
        'confirmed_at',
        'last_used_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'recovery_codes' => 'array',
        'meta' => 'array',
        'is_enabled' => 'boolean',
        'confirmed_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent authenticatable model (polymorphic relationship).
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if two-factor authentication is enabled.
     *
     * @return bool True if 2FA is enabled for this secret
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    /**
     * Enable two-factor authentication for this secret.
     *
     * Marks the secret as enabled and records the confirmation timestamp.
     *
     * @return self Returns the current instance for method chaining
     */
    public function enable(): self
    {
        $this->is_enabled = true;
        $this->confirmed_at = now();
        $this->save();

        return $this;
    }

    /**
     * Disable two-factor authentication for this secret.
     *
     * Marks the secret as disabled without deleting it, allowing future re-enablement.
     *
     * @return self Returns the current instance for method chaining
     */
    public function disable(): self
    {
        $this->is_enabled = false;
        $this->save();

        return $this;
    }

    /**
     * Generate the provisioning URI for QR code generation.
     *
     * Format: otpauth://totp/Issuer:Label?secret=SECRET&issuer=Issuer
     * This URI can be converted to a QR code for scanning by Google Authenticator.
     *
     * @return string The provisioning URI for QR code generation
     */
    public function getProvisioningUri(): string
    {
        $label = $this->label ?? (string) $this->authenticatable_id;
        $issuer = $this->issuer ?? config('app.name', 'Laravel');

        $encodedLabel = $this->encodeLabelForUri($label);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            $encodedLabel,
            $this->secret,
            rawurlencode($issuer)
        );
    }

    /**
     * Encode a label for use in a TOTP provisioning URI.
     *
     * Preserves the '@' symbol for email addresses while encoding other special characters.
     *
     * @param  string  $label  The label to encode
     * @return string Encoded label safe for URI usage
     */
    private function encodeLabelForUri(string $label): string
    {
        return str_replace('@', '%40', rawurlencode($label));
    }

    /**
     * Verify a TOTP code against the stored secret.
     *
     * Uses the TOTP service to validate the provided code against the current
     * time window, accounting for clock drift.
     *
     * @param  string  $code  The 6-8 digit code from Google Authenticator
     * @param  int  $window  Number of time periods to check (default: 1 = ±30 seconds)
     * @return bool True if the code is valid, false otherwise
     */
    public function verifyCode(string $code, int $window = 1): bool
    {
        $totpService = app(TOTPService::class);

        return $totpService->verify($this->secret, $code, $window);
    }

    /**
     * Generate recovery codes for account recovery.
     *
     * Creates a set of one-time use recovery codes that can be used when
     * the user loses access to their authenticator app. Codes are stored
     * as SHA-256 hashes for security.
     *
     * @param  int|null  $count  Number of recovery codes to generate (uses config if null)
     * @param  int|null  $length  Length of each recovery code (uses config if null)
     * @return array<int, string> Array of plain text recovery codes (show once to user)
     */
    public function generateRecoveryCodes(?int $count = null, ?int $length = null): array
    {
        $count = $count ?? config('mfa.recovery_codes.default_count', 8);
        $length = $length ?? config('mfa.recovery_codes.default_length', 10);

        $plainTextCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < $count; $i++) {
            $plainCode = $this->generateSingleRecoveryCode($length);
            $plainTextCodes[] = $plainCode;
            $hashedCodes[] = $this->hashRecoveryCode($plainCode);
        }

        $this->recovery_codes = $hashedCodes;
        $this->save();

        return $plainTextCodes;
    }

    /**
     * Get the characters allowed for recovery code generation.
     *
     * @return string Characters string for recovery code generation
     */
    private function getRecoveryCodeCharacters(): string
    {
        return config('mfa.recovery_codes.characters', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
    }

    /**
     * Get the hashing algorithm for recovery codes.
     *
     * @return string Hashing algorithm name
     */
    private function getRecoveryCodeHashAlgorithm(): string
    {
        return config('mfa.recovery_codes.hash_algorithm', 'sha256');
    }

    /**
     * Generate a single random recovery code.
     *
     * Creates a code using only unambiguous characters to prevent user confusion.
     *
     * @param  int  $length  Length of the recovery code
     * @return string Randomly generated recovery code
     */
    private function generateSingleRecoveryCode(int $length): string
    {
        $characters = $this->getRecoveryCodeCharacters();
        $charactersLength = strlen($characters);
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $randomIndex = random_int(0, $charactersLength - 1);
            $code .= $characters[$randomIndex];
        }

        return $code;
    }

    /**
     * Hash a recovery code using the configured algorithm.
     *
     * @param  string  $plainCode  The plain text recovery code
     * @return string Hashed recovery code
     */
    private function hashRecoveryCode(string $plainCode): string
    {
        $algorithm = $this->getRecoveryCodeHashAlgorithm();

        if ($algorithm === 'bcrypt') {
            return password_hash($plainCode, PASSWORD_BCRYPT);
        }

        return hash($algorithm, $plainCode);
    }

    /**
     * Verify a recovery code hash against the plain code.
     *
     * @param  string  $plainCode  The plain text code to verify
     * @param  string  $hashedCode  The stored hash to compare against
     * @return bool True if the code matches the hash
     */
    private function verifyRecoveryCodeHash(string $plainCode, string $hashedCode): bool
    {
        $algorithm = $this->getRecoveryCodeHashAlgorithm();

        if ($algorithm === 'bcrypt') {
            return password_verify($plainCode, $hashedCode);
        }

        return hash_equals($hashedCode, hash($algorithm, $plainCode));
    }

    /**
     * Verify and consume a recovery code.
     *
     * Checks if the provided code matches any stored recovery code hash.
     * If a match is found, the code is removed (consumed) and cannot be used again.
     *
     * @param  string  $code  The recovery code to verify
     * @return bool True if code is valid and was consumed, false otherwise
     */
    public function verifyRecoveryCode(string $code): bool
    {
        if (empty($this->recovery_codes)) {
            return false;
        }

        foreach ($this->recovery_codes as $index => $storedHash) {
            if ($this->verifyRecoveryCodeHash($code, $storedHash)) {
                $this->removeRecoveryCodeAtIndex($index);

                return true;
            }
        }

        return false;
    }

    /**
     * Remove a recovery code at the specified index.
     *
     * @param  int  $index  The index of the recovery code to remove
     */
    private function removeRecoveryCodeAtIndex(int $index): void
    {
        $codes = $this->recovery_codes;
        unset($codes[$index]);
        $this->recovery_codes = array_values($codes);
        $this->save();
    }

    /**
     * Update the last_used_at timestamp to the current time.
     *
     * @return bool True if the update was successful, false otherwise
     */
    public function touchLastUsedAt(): bool
    {
        return $this->update(['last_used_at' => now()]);
    }
}
