<?php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Integration\Traits;

use AndyDefer\Mfa\Tests\Support\TestUser;
use AndyDefer\Mfa\Tests\TestCase;
use AndyDefer\Mfa\Totp\Models\TwoFactorSecret;
use AndyDefer\Mfa\Totp\Services\TOTPService;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test suite for HasTwoFactorAuthentication trait.
 *
 * Validates 2FA operations including enabling/disabling,
 * code verification, recovery codes, and QR code generation.
 */
final class HasTwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $user;

    private TOTPService $totpService;

    private string $testSecret;

    /**
     * Setup test environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create a test user
        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Arrange: Create TOTP service
        $this->totpService = new TOTPService;

        // Arrange: Generate a test secret
        $this->testSecret = $this->totpService->generateSecret();
    }

    /**
     * Test that twoFactorSecret relationship returns a morphOne relation.
     */
    public function test_two_factor_secret_returns_morph_one_relation(): void
    {
        // Arrange: Create a two factor secret for user
        $secret = TwoFactorSecret::create([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
            'secret' => $this->testSecret,
            'label' => $this->user->email,
            'issuer' => config('app.name'),
            'is_enabled' => false,
        ]);

        // Act: Get the relation
        $relation = $this->user->twoFactorSecret();

        // Assert: Relation is morphOne
        $this->assertInstanceOf(MorphOne::class, $relation);

        // Assert: Can retrieve the secret
        $retrievedSecret = $this->user->twoFactorSecret;
        $this->assertNotNull($retrievedSecret);
        $this->assertEquals($secret->id, $retrievedSecret->id);
    }

    /**
     * Test that getTwoFactorSecret creates a new secret if none exists.
     */
    public function test_get_two_factor_secret_creates_new_secret_when_none_exists(): void
    {
        // Arrange: No secret exists initially
        $this->assertNull($this->user->twoFactorSecret);

        // Act: Get or create secret
        $secret = $this->user->getTwoFactorSecret();

        // Assert: Secret was created
        $this->assertNotNull($secret);
        $this->assertInstanceOf(TwoFactorSecret::class, $secret);
        $this->assertEquals($this->user->id, $secret->authenticatable_id);
        $this->assertEquals($this->user->getMorphClass(), $secret->authenticatable_type);
        $this->assertNotEmpty($secret->secret);
        $this->assertEquals($this->user->email, $secret->label);
        $this->assertFalse($secret->is_enabled);
    }

    /**
     * Test that getTwoFactorSecret returns existing secret without recreating.
     */
    public function test_get_two_factor_secret_returns_existing_secret(): void
    {
        // Arrange: Create a secret manually
        $existingSecret = TwoFactorSecret::create([
            'authenticatable_type' => $this->user->getMorphClass(),
            'authenticatable_id' => $this->user->getKey(),
            'secret' => $this->testSecret,
            'label' => 'custom_label',
            'issuer' => 'Custom Issuer',
            'is_enabled' => true,
        ]);

        // Act: Get the secret
        $secret = $this->user->getTwoFactorSecret();

        // Assert: Returns existing secret, not a new one
        $this->assertEquals($existingSecret->id, $secret->id);
        $this->assertEquals('custom_label', $secret->label);
        $this->assertEquals('Custom Issuer', $secret->issuer);
        $this->assertTrue($secret->is_enabled);
    }

    /**
     * Test that getTwoFactorSecret uses email as label when available.
     */
    public function test_get_two_factor_secret_uses_email_as_label(): void
    {
        // Arrange: User has email
        $this->user->email = 'test@example.com';

        // Act: Get secret
        $secret = $this->user->getTwoFactorSecret();

        // Assert: Label is email
        $this->assertEquals('test@example.com', $secret->label);
    }

    /**
     * Test that getTwoFactorSecret uses primary key as label when email is not available.
     */
    public function test_get_two_factor_secret_uses_primary_key_as_label_when_no_email(): void
    {
        // Arrange: Remove email attribute
        unset($this->user->email);

        // Act: Get secret
        $secret = $this->user->getTwoFactorSecret();

        // Assert: Label is the primary key
        $this->assertEquals((string) $this->user->getKey(), $secret->label);
    }

    /**
     * Test that isTwoFactorEnabled returns false when no secret exists.
     */
    public function test_is_two_factor_enabled_returns_false_when_no_secret(): void
    {
        // Arrange: No secret exists
        $this->assertNull($this->user->twoFactorSecret);

        // Act & Assert: isTwoFactorEnabled returns false
        $this->assertFalse($this->user->isTwoFactorEnabled());
    }

    /**
     * Test that isTwoFactorEnabled returns false when secret exists but is disabled.
     */
    public function test_is_two_factor_enabled_returns_false_when_secret_disabled(): void
    {
        // Arrange: Create disabled secret
        $this->user->getTwoFactorSecret(); // Creates disabled secret

        // Act & Assert: isTwoFactorEnabled returns false
        $this->assertFalse($this->user->isTwoFactorEnabled());
    }

    /**
     * Test that isTwoFactorEnabled returns true when secret is enabled.
     */
    public function test_is_two_factor_enabled_returns_true_when_secret_enabled(): void
    {
        // Arrange: Create and enable secret
        $secret = $this->user->getTwoFactorSecret();
        $secret->enable();

        // Refresh user to reload relationship
        $this->user->refresh();

        // Act & Assert
        $this->assertTrue($this->user->isTwoFactorEnabled());
    }

    /**
     * Test that enableTwoFactor enables 2FA with valid code.
     */
    public function test_enable_two_factor_enables_2fa_with_valid_code(): void
    {
        // Arrange: Get or create secret
        $secret = $this->user->getTwoFactorSecret();
        $this->assertFalse($secret->is_enabled);

        // Generate a valid TOTP code
        $validCode = $this->totpService->now($secret->secret);

        // Act: Enable 2FA with valid code
        $result = $this->user->enableTwoFactor($validCode);

        // Assert: Returns true and secret is enabled
        $this->assertTrue($result);
        $this->assertTrue($secret->fresh()->is_enabled);
    }

    /**
     * Test that enableTwoFactor does not enable 2FA with invalid code.
     */
    public function test_enable_two_factor_does_not_enable_with_invalid_code(): void
    {
        // Arrange: Get secret
        $secret = $this->user->getTwoFactorSecret();
        $this->assertFalse($secret->is_enabled);

        // Act: Try to enable with invalid code
        $result = $this->user->enableTwoFactor('000000');

        // Assert: Returns false and secret remains disabled
        $this->assertFalse($result);
        $this->assertFalse($secret->fresh()->is_enabled);
    }

    /**
     * Test that enableTwoFactor creates secret automatically if none exists.
     */
    public function test_enable_two_factor_creates_secret_automatically(): void
    {
        // Arrange: No secret exists
        $this->assertNull($this->user->twoFactorSecret);

        // Get the secret that will be created
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);

        // Act: Enable 2FA
        $result = $this->user->enableTwoFactor($validCode);

        // Assert: Secret was created and enabled
        $this->assertTrue($result);
        $this->assertNotNull($this->user->twoFactorSecret);
        $this->assertTrue($this->user->twoFactorSecret->is_enabled);
    }

    /**
     * Test that disableTwoFactor disables 2FA when secret exists.
     */
    public function test_disable_two_factor_disables_2fa_when_secret_exists(): void
    {
        // Arrange: Enable 2FA first
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->assertTrue($this->user->enableTwoFactor($validCode));
        $this->assertTrue($this->user->isTwoFactorEnabled());

        // Act: Disable 2FA
        $result = $this->user->disableTwoFactor();

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($this->user->isTwoFactorEnabled());
    }

    /**
     * Test that disableTwoFactor does nothing when no secret exists.
     */
    public function test_disable_two_factor_does_nothing_when_no_secret(): void
    {
        // Arrange: No secret exists
        $this->assertNull($this->user->twoFactorSecret);

        // Act: Disable 2FA (should not throw exception)
        $this->user->disableTwoFactor();

        // Assert: Still no secret
        $this->assertNull($this->user->twoFactorSecret);
    }

    /**
     * Test that verifyTwoFactorCode returns true when 2FA is not enabled.
     */
    public function test_verify_two_factor_code_returns_true_when_2fa_disabled(): void
    {
        // Arrange: 2FA not enabled
        $this->assertFalse($this->user->isTwoFactorEnabled());

        // Act: Verify any code
        $result = $this->user->verifyTwoFactorCode('000000');

        // Assert: Returns true (bypass)
        $this->assertTrue($result);
    }

    /**
     * Test that verifyTwoFactorCode returns true for valid TOTP code.
     */
    public function test_verify_two_factor_code_returns_true_for_valid_totp_code(): void
    {
        // Arrange: Enable 2FA
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->user->enableTwoFactor($validCode);
        $this->assertTrue($this->user->isTwoFactorEnabled());

        // Get a fresh valid code
        $currentValidCode = $this->totpService->now($secret->secret);

        // Act: Verify valid code
        $result = $this->user->verifyTwoFactorCode($currentValidCode);

        // Assert: Returns true
        $this->assertTrue($result);
    }

    /**
     * Test that verifyTwoFactorCode updates last_used_at on successful verification.
     */
    public function test_verify_two_factor_code_updates_last_used_at(): void
    {
        // Arrange: Enable 2FA
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->user->enableTwoFactor($validCode);

        // Initially last_used_at should be null
        $this->assertNull($secret->fresh()->last_used_at);

        // Get a fresh valid code
        $currentValidCode = $this->totpService->now($secret->secret);

        // Act: Verify code
        $this->user->verifyTwoFactorCode($currentValidCode);

        // Assert: last_used_at is updated
        $this->assertNotNull($secret->fresh()->last_used_at);
    }

    /**
     * Test that verifyTwoFactorCode returns false for invalid TOTP code.
     */
    public function test_verify_two_factor_code_returns_false_for_invalid_totp_code(): void
    {
        // Arrange: Enable 2FA
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->user->enableTwoFactor($validCode);

        // Act: Verify invalid code
        $result = $this->user->verifyTwoFactorCode('000000');

        // Assert: Returns false
        $this->assertFalse($result);
    }

    /**
     * Test that verifyTwoFactorCode returns true for valid recovery code.
     */
    public function test_verify_two_factor_code_returns_true_for_valid_recovery_code(): void
    {
        // Arrange: Enable 2FA and generate recovery codes
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->user->enableTwoFactor($validCode);

        $recoveryCodes = $this->user->generateRecoveryCodes();
        $validRecoveryCode = $recoveryCodes[0];

        // Act: Verify recovery code
        $result = $this->user->verifyTwoFactorCode($validRecoveryCode);

        // Assert: Returns true
        $this->assertTrue($result);
    }

    /**
     * Test that verifyTwoFactorCode consumes recovery code after use.
     */
    public function test_verify_two_factor_code_consumes_recovery_code(): void
    {
        // Arrange: Enable 2FA and generate recovery codes
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->user->enableTwoFactor($validCode);

        $recoveryCodes = $this->user->generateRecoveryCodes();
        $validRecoveryCode = $recoveryCodes[0];

        // Act: Use recovery code twice
        $firstUse = $this->user->verifyTwoFactorCode($validRecoveryCode);
        $secondUse = $this->user->verifyTwoFactorCode($validRecoveryCode);

        // Assert: First use works, second fails
        $this->assertTrue($firstUse);
        $this->assertFalse($secondUse);
    }

    /**
     * Test that getTwoFactorQrCodeUri returns provisioning URI.
     */
    public function test_get_two_factor_qr_code_uri_returns_provisioning_uri(): void
    {
        // Arrange: Get secret
        $secret = $this->user->getTwoFactorSecret();
        $uri = $this->user->getTwoFactorQrCodeUri();

        // Assert: URI contains required components
        $this->assertStringContainsString('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=', $uri);
        $this->assertStringContainsString('issuer=', $uri);
        $this->assertStringContainsString('john', $uri);
        $this->assertStringContainsString('example.com', $uri);
    }

    /**
     * Test that generateRecoveryCodes generates new recovery codes.
     */
    public function test_generate_recovery_codes_generates_new_codes(): void
    {
        // Arrange: Get secret
        $secret = $this->user->getTwoFactorSecret();
        $this->assertEmpty($secret->recovery_codes);

        // Act: Generate recovery codes
        $codes = $this->user->generateRecoveryCodes();

        // Assert: Returns array of 8 recovery codes
        $this->assertIsArray($codes);
        $this->assertCount(8, $codes);

        // Assert: Each code has correct format (10 characters, uppercase alphanumeric)
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{10}$/', $code);
        }

        // Assert: Recovery codes are stored hashed in database
        $secret->refresh();
        $this->assertNotEmpty($secret->recovery_codes);
        $this->assertCount(8, $secret->recovery_codes);

        // Assert stored codes are hashed (not equal to plain codes)
        foreach ($secret->recovery_codes as $index => $storedCode) {
            $this->assertNotEquals($codes[$index], $storedCode);
            $this->assertTrue(hash_equals(hash('sha256', $codes[$index]), $storedCode));
        }
    }

    /**
     * Test that generateRecoveryCodes overwrites existing codes.
     */
    public function test_generate_recovery_codes_overwrites_existing_codes(): void
    {
        // Arrange: Generate first set of codes
        $firstCodes = $this->user->generateRecoveryCodes();
        $secret = $this->user->getTwoFactorSecret();
        $firstHashedCodes = $secret->recovery_codes;

        // Act: Generate second set of codes
        $secondCodes = $this->user->generateRecoveryCodes();
        $secret->refresh();
        $secondHashedCodes = $secret->recovery_codes;

        // Assert: Codes are different
        $this->assertNotEquals($firstCodes, $secondCodes);
        $this->assertNotEquals($firstHashedCodes, $secondHashedCodes);
    }

    /**
     * Test that getRecoveryCodes returns hashed recovery codes.
     */
    public function test_get_recovery_codes_returns_hashed_codes(): void
    {
        // Arrange: Generate recovery codes
        $plainCodes = $this->user->generateRecoveryCodes();

        // Refresh user to reload relationship
        $this->user->refresh();

        // Act: Get stored codes
        $storedCodes = $this->user->getRecoveryCodes();

        // Assert: Returns array of hashed codes
        $this->assertIsArray($storedCodes);
        $this->assertCount(8, $storedCodes);

        // Assert: Stored codes are hashed (not equal to plain codes)
        foreach ($storedCodes as $index => $storedCode) {
            $this->assertNotEquals($plainCodes[$index], $storedCode);
            $this->assertTrue(hash_equals(hash('sha256', $plainCodes[$index]), $storedCode));
        }
    }

    /**
     * Test that getRecoveryCodes returns empty array when no secret exists.
     */
    public function test_get_recovery_codes_returns_empty_array_when_no_secret(): void
    {
        // Arrange: No secret exists
        $this->assertNull($this->user->twoFactorSecret);

        // Act: Get recovery codes
        $codes = $this->user->getRecoveryCodes();

        // Assert: Returns empty array
        $this->assertIsArray($codes);
        $this->assertEmpty($codes);
    }

    /**
     * Test complete 2FA workflow from setup to verification.
     */
    public function test_complete_two_factor_workflow(): void
    {
        // Arrange: Start with no 2FA
        $this->assertFalse($this->user->isTwoFactorEnabled());

        // Act: Get secret and QR code
        $secret = $this->user->getTwoFactorSecret();
        $qrCodeUri = $this->user->getTwoFactorQrCodeUri();

        // Assert: Secret created but not enabled
        $this->assertNotNull($secret);
        $this->assertFalse($secret->is_enabled);
        $this->assertStringContainsString('otpauth://', $qrCodeUri);

        // Act: Enable 2FA with valid code
        $validCode = $this->totpService->now($secret->secret);
        $enabled = $this->user->enableTwoFactor($validCode);

        // Assert: 2FA is now enabled
        $this->assertTrue($enabled);
        $this->assertTrue($this->user->isTwoFactorEnabled());

        // Act: Generate recovery codes
        $recoveryCodes = $this->user->generateRecoveryCodes();

        // Assert: Recovery codes generated
        $this->assertCount(8, $recoveryCodes);

        // Act: Verify with TOTP code
        $currentCode = $this->totpService->now($secret->secret);
        $validTotp = $this->user->verifyTwoFactorCode($currentCode);

        // Assert: TOTP verification works
        $this->assertTrue($validTotp);

        // Act: Verify with recovery code
        $validRecovery = $this->user->verifyTwoFactorCode($recoveryCodes[0]);

        // Assert: Recovery code works
        $this->assertTrue($validRecovery);

        // Act: Disable 2FA
        $result = $this->user->disableTwoFactor();

        // Assert: 2FA is disabled
        $this->assertTrue($result);
        $this->assertFalse($this->user->isTwoFactorEnabled());

        // Assert: Verify always returns true when disabled
        $bypassVerification = $this->user->verifyTwoFactorCode('any_code');
        $this->assertTrue($bypassVerification);
    }

    /**
     * Test that two factor secret persists across different authenticatable models.
     */
    public function test_two_factor_secret_works_with_different_authenticatable_models(): void
    {
        // Arrange: Create another user
        $anotherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        // Act: Create secrets for both users
        $secret1 = $this->user->getTwoFactorSecret();
        $secret2 = $anotherUser->getTwoFactorSecret();

        // Assert: Different secrets for different users
        $this->assertNotEquals($secret1->id, $secret2->id);
        $this->assertEquals($this->user->id, $secret1->authenticatable_id);
        $this->assertEquals($anotherUser->id, $secret2->authenticatable_id);
    }

    /**
     * Test that verifyTwoFactorCode returns false when code is empty.
     */
    public function test_verify_two_factor_code_returns_false_for_empty_code(): void
    {
        // Arrange: Enable 2FA
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->user->enableTwoFactor($validCode);

        // Act: Verify empty code
        $result = $this->user->verifyTwoFactorCode('');

        // Assert: Returns false
        $this->assertFalse($result);
    }

    /**
     * Test that multiple verification attempts don't lock account.
     */
    public function test_multiple_verification_attempts_dont_lock_account(): void
    {
        // Arrange: Enable 2FA
        $secret = $this->user->getTwoFactorSecret();
        $validCode = $this->totpService->now($secret->secret);
        $this->user->enableTwoFactor($validCode);

        // Act: Verify invalid codes multiple times
        for ($i = 0; $i < 10; $i++) {
            $result = $this->user->verifyTwoFactorCode('000000');
            $this->assertFalse($result);
        }

        // Assert: Still works with valid code
        $currentValidCode = $this->totpService->now($secret->secret);
        $finalResult = $this->user->verifyTwoFactorCode($currentValidCode);

        $this->assertTrue($finalResult);
    }
}
