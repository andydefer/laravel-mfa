<?php

// tests/Feature/Models/TwoFactorSecretTest.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Feature\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use AndyDefer\Mfa\Tests\Support\TestUser;
use AndyDefer\Mfa\Tests\TestCase;
use AndyDefer\Mfa\Totp\Models\TwoFactorSecret;
use AndyDefer\Mfa\Totp\Services\TOTPService;

/**
 * Test suite for the TwoFactorSecret model.
 *
 * Validates all model functionality including TOTP secret management,
 * recovery codes generation/verification, QR code provisioning,
 * and polymorphic relationships.
 */
final class TwoFactorSecretTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $testUser;

    private TwoFactorSecret $twoFactorSecret;

    private string $testSecret;

    /**
     * Setup test environment.
     *
     * Creates a test user and associates a TOTP secret for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create a test user
        $this->testUser = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Arrange: Create a test secret (base32 encoded)
        $this->testSecret = 'JBSWY3DPEHPK3PXP';

        // Arrange: Create a two-factor secret record
        $this->twoFactorSecret = TwoFactorSecret::create([
            'authenticatable_type' => $this->testUser->getMorphClass(),
            'authenticatable_id' => $this->testUser->getKey(),
            'secret' => $this->testSecret,
            'issuer' => 'TestApp',
            'label' => 'john@example.com',
            'is_enabled' => false,
            'recovery_codes' => null,
            'meta' => ['ip' => '127.0.0.1'],
        ]);
    }

    /**
     * Test that a TwoFactorSecret can be created with valid attributes.
     */
    public function test_two_factor_secret_can_be_created(): void
    {
        // Act
        $secret = TwoFactorSecret::create([
            'authenticatable_type' => $this->testUser->getMorphClass(),
            'authenticatable_id' => $this->testUser->getKey(),
            'secret' => $this->testSecret,
            'issuer' => 'TestApp',
            'label' => 'john@example.com',
        ]);

        // Assert
        $this->assertDatabaseHas('two_factor_secrets', [
            'id' => $secret->id,
            'secret' => $this->testSecret,
            'issuer' => 'TestApp',
        ]);
        $this->assertInstanceOf(TwoFactorSecret::class, $secret);
    }

    /**
     * Test that fillable attributes are properly set.
     */
    public function test_fillable_attributes_are_set(): void
    {
        // Assert
        $this->assertEquals($this->testUser->getMorphClass(), $this->twoFactorSecret->authenticatable_type);
        $this->assertEquals($this->testUser->getKey(), $this->twoFactorSecret->authenticatable_id);
        $this->assertEquals($this->testSecret, $this->twoFactorSecret->secret);
        $this->assertEquals('TestApp', $this->twoFactorSecret->issuer);
        $this->assertEquals('john@example.com', $this->twoFactorSecret->label);
        $this->assertFalse($this->twoFactorSecret->is_enabled);
        $this->assertEquals(['ip' => '127.0.0.1'], $this->twoFactorSecret->meta);
        $this->assertNull($this->twoFactorSecret->recovery_codes);
    }

    /**
     * Test that casts work correctly.
     */
    public function test_casts_work_correctly(): void
    {
        // Assert
        $this->assertIsArray($this->twoFactorSecret->meta);
        $this->assertIsBool($this->twoFactorSecret->is_enabled);

        // confirmed_at and last_used_at can be null initially
        $this->assertTrue($this->twoFactorSecret->confirmed_at === null ||
            $this->twoFactorSecret->confirmed_at instanceof CarbonInterface);
        $this->assertTrue($this->twoFactorSecret->last_used_at === null ||
            $this->twoFactorSecret->last_used_at instanceof CarbonInterface);

        // created_at and updated_at are Carbon instances by default in Laravel
        // They can be Carbon or CarbonImmutable depending on configuration
        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->created_at);
        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->updated_at);

        // recovery_codes can be null or array
        $this->assertTrue($this->twoFactorSecret->recovery_codes === null ||
            is_array($this->twoFactorSecret->recovery_codes));
    }

    /**
     * Test that authenticatable relationship returns the correct parent model.
     */
    public function test_authenticatable_returns_correct_parent_model(): void
    {
        // Act
        $authenticatable = $this->twoFactorSecret->authenticatable;

        // Assert
        $this->assertNotNull($authenticatable);
        $this->assertInstanceOf(TestUser::class, $authenticatable);
        $this->assertEquals($this->testUser->id, $authenticatable->id);
    }

    /**
     * Test that isEnabled returns false when 2FA is not enabled.
     */
    public function test_is_enabled_returns_false_when_not_enabled(): void
    {
        // Assert
        $this->assertFalse($this->twoFactorSecret->isEnabled());
    }

    /**
     * Test that isEnabled returns true after enabling.
     */
    public function test_is_enabled_returns_true_after_enabling(): void
    {
        // Act
        $this->twoFactorSecret->enable();

        // Assert
        $this->assertTrue($this->twoFactorSecret->isEnabled());
        $this->assertNotNull($this->twoFactorSecret->confirmed_at);
        // Use CarbonInterface instead of CarbonImmutable to support both Carbon and CarbonImmutable
        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->confirmed_at);
    }

    /**
     * Test that disable correctly disables two-factor authentication.
     */
    public function test_disable_correctly_disables_two_factor(): void
    {
        // Arrange: First enable
        $this->twoFactorSecret->enable();
        $this->assertTrue($this->twoFactorSecret->isEnabled());

        // Act: Then disable
        $this->twoFactorSecret->disable();

        // Assert
        $this->assertFalse($this->twoFactorSecret->isEnabled());
    }

    /**
     * Test that getProvisioningUri returns correct format.
     */
    public function test_get_provisioning_uri_returns_correct_format(): void
    {
        // Act
        $uri = $this->twoFactorSecret->getProvisioningUri();

        // Assert
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        // The email is URL encoded, so @ becomes %40
        $this->assertStringContainsString('TestApp:john%40example.com', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=TestApp', $uri);
    }

    /**
     * Test that getProvisioningUri uses fallback values when issuer/label are missing.
     */
    public function test_get_provisioning_uri_uses_fallback_values(): void
    {
        // Arrange: Create secret without issuer and label
        $secret = TwoFactorSecret::create([
            'authenticatable_type' => $this->testUser->getMorphClass(),
            'authenticatable_id' => $this->testUser->getKey(),
            'secret' => $this->testSecret,
        ]);

        // Act
        $uri = $secret->getProvisioningUri();

        // Assert
        $this->assertStringContainsString('issuer=' . rawurlencode(config('app.name')), $uri);
        $this->assertStringContainsString((string) $this->testUser->getKey(), $uri);
    }

    /**
     * Test that verifyCode returns true for a valid TOTP code.
     */
    public function test_verify_code_returns_true_for_valid_code(): void
    {
        // Arrange: Mock the TOTPService to return true
        $mockService = \Mockery::mock(TOTPService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->with($this->testSecret, '123456', 1)
            ->andReturn(true);
        $this->app->instance(TOTPService::class, $mockService);

        // Act & Assert
        $this->assertTrue($this->twoFactorSecret->verifyCode('123456'));
    }

    /**
     * Test that verifyCode returns false for an invalid TOTP code.
     */
    public function test_verify_code_returns_false_for_invalid_code(): void
    {
        // Arrange: Mock the TOTPService to return false
        $mockService = \Mockery::mock(TOTPService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->with($this->testSecret, '999999', 1)
            ->andReturn(false);
        $this->app->instance(TOTPService::class, $mockService);

        // Act & Assert
        $this->assertFalse($this->twoFactorSecret->verifyCode('999999'));
    }

    /**
     * Test that generateRecoveryCodes creates the correct number of codes.
     */
    public function test_generate_recovery_codes_creates_correct_number_of_codes(): void
    {
        // Act
        $codes = $this->twoFactorSecret->generateRecoveryCodes(8, 10);

        // Assert
        $this->assertCount(8, $codes);
        $this->assertIsArray($this->twoFactorSecret->recovery_codes);
        $this->assertCount(8, $this->twoFactorSecret->recovery_codes);
    }

    /**
     * Test that generated recovery codes have the correct format.
     * Note: The current implementation generates codes without dash (10 characters)
     */
    public function test_generated_recovery_codes_have_correct_format(): void
    {
        // Act
        $codes = $this->twoFactorSecret->generateRecoveryCodes(2, 10);

        // Assert: Check format based on actual implementation (no dash)
        foreach ($codes as $code) {
            // The current implementation generates 10-character codes without dash
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{10}$/', $code);
        }
    }

    /**
     * Test that recovery codes are stored as hashed values.
     */
    public function test_recovery_codes_are_stored_as_hashed_values(): void
    {
        // Act
        $plainCodes = $this->twoFactorSecret->generateRecoveryCodes(2, 10);
        $storedCodes = $this->twoFactorSecret->recovery_codes;

        // Assert
        $this->assertNotEmpty($storedCodes);
        foreach ($plainCodes as $index => $plainCode) {
            $this->assertEquals(hash('sha256', $plainCode), $storedCodes[$index]);
        }
    }

    /**
     * Test that verifyRecoveryCode returns true for a valid recovery code.
     */
    public function test_verify_recovery_code_returns_true_for_valid_code(): void
    {
        // Arrange: Generate recovery codes
        $codes = $this->twoFactorSecret->generateRecoveryCodes(2, 10);
        $validCode = $codes[0];

        // Act
        $result = $this->twoFactorSecret->verifyRecoveryCode($validCode);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test that verifyRecoveryCode consumes the code after verification.
     */
    public function test_verify_recovery_code_consumes_the_code(): void
    {
        // Arrange: Generate recovery codes
        $codes = $this->twoFactorSecret->generateRecoveryCodes(2, 10);
        $validCode = $codes[0];
        $initialCount = count($this->twoFactorSecret->recovery_codes);

        // Act
        $this->twoFactorSecret->verifyRecoveryCode($validCode);

        // Assert
        $this->assertCount($initialCount - 1, $this->twoFactorSecret->recovery_codes);
    }

    /**
     * Test that the same recovery code cannot be used twice.
     */
    public function test_same_recovery_code_cannot_be_used_twice(): void
    {
        // Arrange: Generate recovery codes
        $codes = $this->twoFactorSecret->generateRecoveryCodes(1, 10);
        $validCode = $codes[0];

        // Act: First use
        $firstResult = $this->twoFactorSecret->verifyRecoveryCode($validCode);
        // Act: Second use
        $secondResult = $this->twoFactorSecret->verifyRecoveryCode($validCode);

        // Assert
        $this->assertTrue($firstResult);
        $this->assertFalse($secondResult);
    }

    /**
     * Test that verifyRecoveryCode returns false for an invalid code.
     */
    public function test_verify_recovery_code_returns_false_for_invalid_code(): void
    {
        // Act
        $result = $this->twoFactorSecret->verifyRecoveryCode('INVALID-CODE');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that verifyRecoveryCode returns false when no recovery codes exist.
     */
    public function test_verify_recovery_code_returns_false_when_no_codes_exist(): void
    {
        // Act
        $result = $this->twoFactorSecret->verifyRecoveryCode('ANY-CODE');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test that different secrets can be stored.
     */
    public function test_different_secrets_can_be_stored(): void
    {
        // Arrange
        $secrets = ['SECRET1', 'SECRET2', 'SECRET3'];

        // Act & Assert
        foreach ($secrets as $secret) {
            $twoFactorSecret = TwoFactorSecret::create([
                'authenticatable_type' => $this->testUser->getMorphClass(),
                'authenticatable_id' => $this->testUser->getKey(),
                'secret' => $secret,
            ]);

            $this->assertEquals($secret, $twoFactorSecret->secret);
            $this->assertDatabaseHas('two_factor_secrets', ['id' => $twoFactorSecret->id, 'secret' => $secret]);
        }
    }

    /**
     * Test that meta data can be stored as JSON.
     */
    public function test_meta_data_can_be_stored_as_json(): void
    {
        // Arrange
        $metaList = [
            ['ip' => '127.0.0.1'],
            ['ip' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0'],
            ['device' => 'iPhone', 'os' => 'iOS 17'],
            null,
        ];

        // Act & Assert
        foreach ($metaList as $meta) {
            $secret = TwoFactorSecret::create([
                'authenticatable_type' => $this->testUser->getMorphClass(),
                'authenticatable_id' => $this->testUser->getKey(),
                'secret' => $this->testSecret,
                'meta' => $meta,
            ]);

            $this->assertEquals($meta, $secret->meta);
            $this->assertDatabaseHas('two_factor_secrets', ['id' => $secret->id]);
        }
    }

    /**
     * Test that recovery codes can be stored and retrieved correctly.
     */
    public function test_recovery_codes_can_be_stored_and_retrieved_correctly(): void
    {
        // Arrange
        $recoveryCodes = hash('sha256', 'CODE-12345');

        // Act
        $this->twoFactorSecret->update(['recovery_codes' => [$recoveryCodes]]);
        $this->twoFactorSecret->refresh();

        // Assert
        $this->assertIsArray($this->twoFactorSecret->recovery_codes);
        $this->assertContains($recoveryCodes, $this->twoFactorSecret->recovery_codes);
    }

    /**
     * Test that multiple TwoFactorSecrets can be associated with different authenticatable models.
     */
    public function test_multiple_secrets_with_different_authenticatable_models(): void
    {
        // Arrange: Create another test user
        $anotherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        // Act
        $secret1 = $this->twoFactorSecret;
        $secret2 = TwoFactorSecret::create([
            'authenticatable_type' => $anotherUser->getMorphClass(),
            'authenticatable_id' => $anotherUser->getKey(),
            'secret' => 'ANOTHER-SECRET',
        ]);

        // Assert
        $this->assertEquals($this->testUser->id, $secret1->authenticatable_id);
        $this->assertEquals($anotherUser->id, $secret2->authenticatable_id);
    }

    /**
     * Test that confirmed_at is properly set when enabling 2FA.
     */
    public function test_confirmed_at_is_set_when_enabling(): void
    {
        // Arrange
        $this->assertNull($this->twoFactorSecret->confirmed_at);

        // Act
        $this->twoFactorSecret->enable();

        // Assert
        $this->assertNotNull($this->twoFactorSecret->confirmed_at);
        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->confirmed_at);
    }

    /**
     * Test that last_used_at can be updated.
     */
    public function test_last_used_at_can_be_updated(): void
    {
        // Arrange
        $this->assertNull($this->twoFactorSecret->last_used_at);

        // Act
        $this->twoFactorSecret->touchLastUsedAt();

        // Assert
        $this->assertNotNull($this->twoFactorSecret->refresh()->last_used_at);
        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->last_used_at);
    }
}
