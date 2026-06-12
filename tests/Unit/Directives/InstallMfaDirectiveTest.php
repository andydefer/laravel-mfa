<?php

// tests/Unit/Directives/InstallMfaDirectiveTest.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\PrimitiveType;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\FileSystemService;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Mfa\Directives\InstallMfaDirective;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Builder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class InstallMfaDirectiveTest extends TestCase
{
    private Kernel&MockObject $kernel;

    private Application&MockObject $app;

    private FileSystemService&MockObject $filesystem;

    private DatabaseManager&MockObject $db;

    private Connection&MockObject $connection;

    private Builder&MockObject $schemaBuilder;

    private DirectiveInteractionService&MockObject $interaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = $this->createMock(Kernel::class);
        $this->app = $this->createMock(Application::class);
        $this->filesystem = $this->createMock(FileSystemService::class);

        $this->db = $this->createMock(DatabaseManager::class);
        $this->connection = $this->createMock(Connection::class);
        $this->schemaBuilder = $this->createMock(Builder::class);

        $this->db->method('connection')->willReturn($this->connection);
        $this->connection->method('getSchemaBuilder')->willReturn($this->schemaBuilder);

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
    }

    private function createDirectiveWithOptions(array $options = [], array $fileExistsMap = []): InstallMfaDirective
    {
        // ✅ CORRECTION : Mock basePath avec gestion correcte des paramètres
        $this->app->method('basePath')->willReturnCallback(function ($path = '') {
            if ($path === '' || $path === null) {
                return '/fake/project';
            }
            // Enlever le slash initial si présent
            $path = ltrim($path, '/');

            return '/fake/project/'.$path;
        });

        $this->app->method('databasePath')->willReturnCallback(function ($path = '') {
            if ($path === '' || $path === null) {
                return '/fake/project/database';
            }
            $path = ltrim($path, '/');

            return '/fake/project/database/'.$path;
        });

        $this->filesystem->method('exists')->willReturnCallback(function ($path) use ($fileExistsMap) {
            // Vérification directe dans le map
            if (isset($fileExistsMap[$path])) {
                return $fileExistsMap[$path];
            }

            // Vérification par pattern
            foreach ($fileExistsMap as $pattern => $exists) {
                if (str_contains($path, $pattern)) {
                    return $exists;
                }
            }

            // Valeurs par défaut pour les chemins courants
            if (str_contains($path, '/vendor/andydefer/laravel-mfa/config/mfa.php')) {
                return true;
            }
            if (str_contains($path, '/vendor/andydefer/laravel-mfa')) {
                return true;
            }
            if (str_contains($path, '/database/migrations/')) {
                return true;
            }

            return false;
        });

        $this->filesystem->method('isDirectory')->willReturnCallback(function ($path) use ($fileExistsMap) {
            foreach ($fileExistsMap as $pattern => $exists) {
                if (str_contains($path, $pattern) && $exists) {
                    return true;
                }
            }

            // Valeurs par défaut
            if (str_contains($path, '/database/migrations')) {
                return true;
            }
            if (str_contains($path, '/vendor')) {
                return true;
            }

            return false;
        });

        $this->filesystem->method('glob')->willReturnCallback(function ($pattern) {
            if (str_contains($pattern, 'one_time_passwords')) {
                return ['/fake/path/2024_01_01_000001_create_one_time_passwords_table.php'];
            }
            if (str_contains($pattern, 'two_factor_secrets')) {
                return ['/fake/path/2024_01_01_000002_create_two_factor_secrets_table.php'];
            }

            return [];
        });

        $this->filesystem->method('get')->willReturnCallback(function ($path) {
            return '<?php return []; ?>';
        });

        $this->filesystem->method('put')->willReturnCallback(function ($path, $content) {
            return strlen($content);
        });

        $this->filesystem->method('makeDirectory')->willReturn(true);

        // Construction des options
        $optionsCollection = new ParameterVOCollection;
        foreach ($options as $key => $value) {
            $reflection = new \ReflectionClass($optionsCollection);
            $itemsProperty = $reflection->getProperty('items');
            $items = $itemsProperty->getValue($optionsCollection);

            $paramVO = new ParameterVO(
                name: $key,
                value: $value,
                type: PrimitiveType::BOOL
            );
            $items[] = $paramVO;
            $itemsProperty->setValue($optionsCollection, $items);
        }

        $context = new DirectiveContext(
            laravelBootstrapper: new LaravelBootstrapperContext,
            blueprint: new DirectiveBlueprintRecord(
                InstallMfaDirective::class,
                'mfa-install',
                'Install the Laravel MFA package'
            ),
            aliases: new StringTypedCollection,
            shouldBootLaravel: true,
        );

        $context->setOptions($optionsCollection);

        return new InstallMfaDirective(
            $context,
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
        );
    }

    // ============================================================================
    // Signature Tests
    // ============================================================================

    public function test_get_signature_returns_mfa_install(): void
    {
        $directive = new InstallMfaDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext,
                new DirectiveBlueprintRecord(InstallMfaDirective::class, '', ''),
                new StringTypedCollection,
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
        );

        $signature = $directive->getSignature();

        $this->assertStringContainsString('mfa-install', $signature);
        $this->assertStringContainsString('--force', $signature);
        $this->assertStringContainsString('--no-migrate', $signature);
        $this->assertStringContainsString('--without-otp', $signature);
        $this->assertStringContainsString('--without-totp', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $directive = new InstallMfaDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext,
                new DirectiveBlueprintRecord(InstallMfaDirective::class, '', ''),
                new StringTypedCollection,
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
        );

        $description = $directive->getDescription();

        $this->assertSame('Install the Laravel MFA package for multi-factor authentication management (OTP + TOTP)', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = new InstallMfaDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext,
                new DirectiveBlueprintRecord(InstallMfaDirective::class, '', ''),
                new StringTypedCollection,
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
        );

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('mfa-setup'));
        $this->assertTrue($aliases->contains('mfa-configure'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $directive = new InstallMfaDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext,
                new DirectiveBlueprintRecord(InstallMfaDirective::class, '', ''),
                new StringTypedCollection,
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
        );

        $this->assertTrue($directive->shouldBootLaravel());
    }

    // ============================================================================
    // Confirmation Tests
    // ============================================================================

    public function test_execute_cancels_when_user_declines_confirmation(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(false);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_proceeds_when_user_confirms(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Force Option Tests
    // ============================================================================

    public function test_execute_proceeds_with_force_option(): void
    {
        $this->interaction->expects($this->never())->method('confirm');

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->schemaBuilder->method('hasTable')->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => true],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Package Not Found Tests
    // ============================================================================

    public function test_execute_returns_failure_when_package_not_found(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => false,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    // ============================================================================
    // Config File Tests
    // ============================================================================

    public function test_execute_returns_failure_when_config_source_missing(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => false,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_warns_when_config_already_exists_without_force(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->exactly(2))
            ->method('confirm')
            ->willReturnCallback(function ($question) {
                if ($question === 'Do you want to reinstall? This may overwrite existing files.') {
                    return true;
                }
                if ($question === 'Continue with installation?') {
                    return true;
                }

                return false;
            });

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Migration File Tests
    // ============================================================================

    public function test_execute_returns_failure_when_migration_source_missing(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => false,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_warns_when_migration_already_exists_without_force(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->exactly(2))
            ->method('confirm')
            ->willReturnCallback(function ($question) {
                if ($question === 'Do you want to reinstall? This may overwrite existing files.') {
                    return true;
                }
                if ($question === 'Continue with installation?') {
                    return true;
                }

                return false;
            });

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
                '/fake/project/database/migrations/2024_01_01_000001_create_one_time_passwords_table.php' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Migration Execution Tests
    // ============================================================================

    public function test_execute_skips_migrations_when_no_migrate_option(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $this->kernel->expects($this->never())->method('call');

        $directive = $this->createDirectiveWithOptions(
            ['force' => false, 'no-migrate' => true],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_failure_when_migration_fails(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(1);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    // ============================================================================
    // Database Table Verification Tests
    // ============================================================================

    public function test_execute_continues_when_table_does_not_exist(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Without OTP Tests
    // ============================================================================

    public function test_execute_skips_otp_when_without_otp_option(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false, 'without-otp' => true],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Without TOTP Tests
    // ============================================================================

    public function test_execute_skips_totp_when_without_totp_option(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(false);

        $this->interaction->expects($this->once())
            ->method('confirm')
            ->with('Continue with installation?')
            ->willReturn(true);

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $directive = $this->createDirectiveWithOptions(
            ['force' => false, 'without-totp' => true],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Overwrite Tests
    // ============================================================================

    public function test_execute_overwrites_config_with_force(): void
    {
        $this->schemaBuilder->method('hasTable')->willReturn(true);

        $this->interaction->expects($this->never())->method('confirm');

        $this->kernel->expects($this->once())
            ->method('call')
            ->with('migrate', ['--force' => true])
            ->willReturn(0);

        $this->filesystem->expects($this->atLeastOnce())
            ->method('put')
            ->willReturnCallback(function ($path, $content) {
                $this->assertStringContainsString('config/mfa.php', $path, "Path should contain config/mfa.php, got: {$path}");
                $this->assertIsString($content);

                return strlen($content);
            });

        $directive = $this->createDirectiveWithOptions(
            ['force' => true],
            [
                '/fake/project/vendor/andydefer/laravel-mfa' => true,
                '/fake/project/vendor/andydefer/laravel-mfa/config/mfa.php' => true,
                '/fake/project/config/mfa.php' => true,
                '/fake/project/database/migrations/' => true,
                '/fake/project/database/migrations/2024_01_01_000001_create_one_time_passwords_table.php' => true,
            ]
        );

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ============================================================================
    // Alias Tests
    // ============================================================================

    public function test_alias_mfa_setup_exists(): void
    {
        $directive = new InstallMfaDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext,
                new DirectiveBlueprintRecord(InstallMfaDirective::class, '', ''),
                new StringTypedCollection,
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
        );

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('mfa-setup'));
    }

    public function test_alias_mfa_configure_exists(): void
    {
        $directive = new InstallMfaDirective(
            new DirectiveContext(
                new LaravelBootstrapperContext,
                new DirectiveBlueprintRecord(InstallMfaDirective::class, '', ''),
                new StringTypedCollection,
                true
            ),
            $this->interaction,
            $this->kernel,
            $this->app,
            $this->filesystem,
            $this->db,
        );

        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('mfa-configure'));
    }
}
