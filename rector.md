# Rector Refactoring Report
*Generated: dim. 26 avril 2026 17:43:05 WAT*


24 files with changes
=====================

1) /home/andy-kani/pro/sites/packages/laravel-otp/src/Core/Commands/CleanupMfaCommand.php:537

    ---------- begin diff ----------
@@ Line 537 @@
         $this->addStatisticRowIfNeeded($rows, 'Disabled 2FA secrets deleted', $statistics['totp_disabled'], $statistics['totp_disabled'] > 0 && !$this->option('otp-only'));
         $this->addStatisticRowIfNeeded($rows, 'Unused 2FA secrets deleted', $statistics['totp_expired'], $statistics['totp_expired'] > 0 && !$this->option('otp-only'));

-        if (count($rows) > 0) {
+        if ($rows !== []) {
             $rows[] = ['━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━'];
             $rows[] = ['Total records deleted', $statistics['total']];
         } else {
    ----------- end diff -----------

Applied rules:
 * CountArrayToEmptyArrayComparisonRector


2) /home/andy-kani/pro/sites/packages/laravel-otp/src/Core/Helpers/TranslationHelper.php:39

    ---------- begin diff ----------
@@ Line 39 @@
     {
         $locale = self::resolvePackageLocale();

-        return Lang::get("mfa::{$key}", $replace, $locale);
+        return Lang::get('mfa::' . $key, $replace, $locale);
     }

     /**
    ----------- end diff -----------

Applied rules:
 * EncapsedStringsToSprintfRector


3) /home/andy-kani/pro/sites/packages/laravel-otp/src/Core/Services/MfaInstallerService.php:153

    ---------- begin diff ----------
@@ Line 153 @@
         if ($includeOtp && $this->hasOtpTables()) {
             return true;
         }
-
-        if ($includeTotp && $this->hasTotpTables()) {
-            return true;
-        }
-
-        return false;
+        return $includeTotp && $this->hasTotpTables();
     }

     /**
@@ Line 245 @@
             return [];
         }

-        $files = array_filter($files, function ($file) use ($includeOtp, $includeTotp) {
+        $files = array_filter($files, function (string $file) use ($includeOtp, $includeTotp): bool {
             $isOtpMigration = str_contains($file, 'one_time_passwords');
             $isTotpMigration = str_contains($file, 'two_factor_secrets');

@@ Line 252 @@
             if ($isOtpMigration && !$includeOtp) {
                 return false;
             }
-
-            if ($isTotpMigration && !$includeTotp) {
-                return false;
-            }
-
-            return true;
+            return !($isTotpMigration && !$includeTotp);
         });

         return array_values($files);
@@ Line 319 @@
             Artisan::call('migrate', ['--force' => true]);
             $command->info(Artisan::output());
             $command->info('   ✅ Migrations completed successfully.');
-        } catch (RuntimeException $exception) {
-            $command->error('   ❌ Migration failed: ' . $exception->getMessage());
+        } catch (RuntimeException $runtimeException) {
+            $command->error('   ❌ Migration failed: ' . $runtimeException->getMessage());
         }
     }

@@ Line 337 @@
                     return true;
                 }
             }
-        } catch (RuntimeException $exception) {
+        } catch (RuntimeException $runtimeException) {
             // Database connection may not be configured yet
             return false;
         }
@@ Line 358 @@
                     return true;
                 }
             }
-        } catch (RuntimeException $exception) {
+        } catch (RuntimeException $runtimeException) {
             // Database connection may not be configured yet
             return false;
         }
    ----------- end diff -----------

Applied rules:
 * SimplifyIfReturnBoolRector
 * CatchExceptionNameMatchingTypeRector
 * ClosureReturnTypeRector
 * AddArrayFunctionClosureParamTypeRector


4) /home/andy-kani/pro/sites/packages/laravel-otp/src/MfaServiceProvider.php:96

    ---------- begin diff ----------
@@ Line 96 @@

     /**
      * Get the source path for the configuration file.
-     *
-     * @return string
      */
     private function getConfigSourcePath(): string
     {
@@ Line 106 @@

     /**
      * Get the destination path for the configuration file.
-     *
-     * @return string
      */
     private function getConfigDestinationPath(): string
     {
@@ Line 116 @@

     /**
      * Get the source path for the migrations directory.
-     *
-     * @return string
      */
     private function getMigrationsSourcePath(): string
     {
@@ Line 126 @@

     /**
      * Get the destination path for the migrations directory.
-     *
-     * @return string
      */
     private function getMigrationsDestinationPath(): string
     {
@@ Line 202 @@
      */
     private function bindTotpService(): void
     {
-        $this->app->singleton(TOTPService::class, function ($app) {
+        $this->app->singleton(TOTPService::class, function ($app): TOTPService {
             return new TOTPService(
                 period: config('mfa.totp.period', 30),
                 digits: config('mfa.totp.digits', 6),
    ----------- end diff -----------

Applied rules:
 * RemoveUselessReturnTagRector
 * ClosureReturnTypeRector


5) /home/andy-kani/pro/sites/packages/laravel-otp/src/Otp/Models/OneTimePassword.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Otp\Models;

+use Illuminate\Support\Carbon;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\MorphTo;
 use Illuminate\Support\Facades\Hash;
@@ Line 25 @@
  * @property array|null $meta
  * @property int $attempts
  * @property int $max_attempts
- * @property \Illuminate\Support\Carbon $expires_at
- * @property \Illuminate\Support\Carbon|null $verified_at
- * @property \Illuminate\Support\Carbon|null $used_at
- * @property \Illuminate\Support\Carbon|null $cancelled_at
- * @property \Illuminate\Support\Carbon $created_at
- * @property \Illuminate\Support\Carbon $updated_at
+ * @property Carbon $expires_at
+ * @property Carbon|null $verified_at
+ * @property Carbon|null $used_at
+ * @property Carbon|null $cancelled_at
+ * @property Carbon $created_at
+ * @property Carbon $updated_at
  */
 final class OneTimePassword extends Model
 {
@@ Line 65 @@

     /**
      * Get the parent otpable model (polymorphic relationship).
-     *
-     * @return MorphTo
      */
     public function otpable(): MorphTo
     {
@@ Line 201 @@
      */
     public function incrementAttempts(): self
     {
-        $this->attempts++;
+        ++$this->attempts;
         $this->save();

         return $this;
    ----------- end diff -----------

Applied rules:
 * PostIncDecToPreIncDecRector
 * RemoveUselessReturnTagRector


6) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Commands/CleanupMfaCommandTest.php:5

    ---------- begin diff ----------
@@ Line 5 @@

 namespace Kani\Mfa\Tests\Unit\Commands;

-use Carbon\Carbon;
+use Kani\Mfa\Core\Commands\CleanupMfaCommand;
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Illuminate\Support\Facades\DB;
 use Kani\Mfa\Otp\Models\OneTimePassword;
@@ Line 38 @@
     public function test_command_can_be_instantiated(): void
     {
         // Act
-        $command = $this->app->make(\Kani\Mfa\Core\Commands\CleanupMfaCommand::class);
+        $command = $this->app->make(CleanupMfaCommand::class);

         // Assert
-        $this->assertInstanceOf(\Kani\Mfa\Core\Commands\CleanupMfaCommand::class, $command);
+        $this->assertInstanceOf(CleanupMfaCommand::class, $command);
     }

     /**
@@ Line 50 @@
     public function test_command_has_correct_signature(): void
     {
         // Act
-        $command = $this->app->make(\Kani\Mfa\Core\Commands\CleanupMfaCommand::class);
+        $command = $this->app->make(CleanupMfaCommand::class);

         // Assert
         $this->assertEquals('mfa:cleanup', $command->getName());
    ----------- end diff -----------

Applied rules:


7) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Helpers/TranslationHelperTest.php:31

    ---------- begin diff ----------
@@ Line 31 @@
         $defaultUserName = TranslationHelper::trans('messages.default_user_name');

         // Assert: English translations are returned
-        $this->assertEquals('Your verification code - Test App', $subject);
-        $this->assertEquals('Hello %s!', $greeting);
-        $this->assertEquals('User', $defaultUserName);
+        $this->assertSame('Your verification code - Test App', $subject);
+        $this->assertSame('Hello %s!', $greeting);
+        $this->assertSame('User', $defaultUserName);
     }

     /**
@@ Line 52 @@
         $defaultUserName = TranslationHelper::trans('messages.default_user_name');

         // Assert: French translations are returned
-        $this->assertEquals('Votre code de vérification - Test App', $subject);
-        $this->assertEquals('Bonjour %s !', $greeting);
-        $this->assertEquals('Utilisateur', $defaultUserName);
+        $this->assertSame('Votre code de vérification - Test App', $subject);
+        $this->assertSame('Bonjour %s !', $greeting);
+        $this->assertSame('Utilisateur', $defaultUserName);
     }

     /**
@@ Line 71 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);

         // Assert: Falls back to English translation
-        $this->assertEquals('Your verification code - Test App', $subject);
+        $this->assertSame('Your verification code - Test App', $subject);
     }

     /**
@@ Line 88 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);

         // Assert: Default English translation is used
-        $this->assertEquals('Your verification code - Test App', $subject);
+        $this->assertSame('Your verification code - Test App', $subject);
     }

     /**
@@ Line 105 @@
         $cancelSuccess = TranslationHelper::trans('messages.cancel_success', ['count' => 3]);

         // Assert: Placeholders are replaced with provided values
-        $this->assertEquals('Your verification code - MyAwesomeApp', $subject);
-        $this->assertEquals('This code will expire in 5 minute(s).', $expiresIn);
-        $this->assertEquals('3 OTP(s) cancelled successfully.', $cancelSuccess);
+        $this->assertSame('Your verification code - MyAwesomeApp', $subject);
+        $this->assertSame('This code will expire in 5 minute(s).', $expiresIn);
+        $this->assertSame('3 OTP(s) cancelled successfully.', $cancelSuccess);
     }

     /**
@@ Line 124 @@
         ]);

         // Assert: The placeholder is replaced correctly
-        $this->assertEquals('Invalid OTP code. You have 2 attempts remaining.', $message);
+        $this->assertSame('Invalid OTP code. You have 2 attempts remaining.', $message);
     }

     /**
@@ Line 139 @@
         $result = TranslationHelper::trans('messages.non_existent_key');

         // Assert: Returns the key itself (Laravel default behavior)
-        $this->assertEquals('mfa::messages.non_existent_key', $result);
+        $this->assertSame('mfa::messages.non_existent_key', $result);
     }

     /**
@@ Line 156 @@
         $translatedMessage = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

         // Assert: Package uses English but application locale remains French
-        $this->assertEquals('Your verification code - Test', $translatedMessage);
+        $this->assertSame('Your verification code - Test', $translatedMessage);
         $this->assertEquals('fr', app()->getLocale());
     }

@@ Line 174 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

         // Assert: Falls back to French instead
-        $this->assertEquals('Votre code de vérification - Test', $subject);
+        $this->assertSame('Votre code de vérification - Test', $subject);
     }

     /**
@@ Line 189 @@
         $ignoreMessage = TranslationHelper::trans('messages.ignore_request');

         // Assert: Returns the message as-is without errors
-        $this->assertEquals(
+        $this->assertSame(
             'If you did not request this verification, please ignore this email.',
             $ignoreMessage
         );
@@ Line 209 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

         // Assert: Falls back to English default without errors
-        $this->assertEquals('Your verification code - Test', $subject);
+        $this->assertSame('Your verification code - Test', $subject);
     }
 }
    ----------- end diff -----------

Applied rules:
 * AssertEqualsToSameRector


8) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/MfaServiceProviderTest.php:5

    ---------- begin diff ----------
@@ Line 5 @@

 namespace Kani\Mfa\Tests\Unit;

+use ReflectionClass;
+use ReflectionMethod;
+use ReflectionParameter;
+use Illuminate\Contracts\Console\Kernel;
 use Kani\Mfa\Core\Commands\CleanupMfaCommand;
 use Kani\Mfa\Core\Commands\InstallMfaCommand;
 use Kani\Mfa\Otp\Contracts\CodeGeneratorInterface;
@@ Line 122 @@
         $provider->boot();

         // Assert: Translation helper should return English text using mfa namespace
-        $this->assertEquals('Your verification code - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
-        $this->assertEquals('Hello %s!', TranslationHelper::trans('messages.greeting'));
-        $this->assertEquals('User', TranslationHelper::trans('messages.default_user_name'));
+        $this->assertSame('Your verification code - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
+        $this->assertSame('Hello %s!', TranslationHelper::trans('messages.greeting'));
+        $this->assertSame('User', TranslationHelper::trans('messages.default_user_name'));
     }

     /**
@@ Line 141 @@
         $provider->boot();

         // Assert: Translation helper should return French text
-        $this->assertEquals('Votre code de vérification - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
-        $this->assertEquals('Bonjour %s !', TranslationHelper::trans('messages.greeting'));
-        $this->assertEquals('Utilisateur', TranslationHelper::trans('messages.default_user_name'));
+        $this->assertSame('Votre code de vérification - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
+        $this->assertSame('Bonjour %s !', TranslationHelper::trans('messages.greeting'));
+        $this->assertSame('Utilisateur', TranslationHelper::trans('messages.default_user_name'));
     }

     /**
@@ Line 381 @@
         // Act: Register the service provider and resolve OtpService
         $provider->register();

-        $reflection = new \ReflectionClass(OtpService::class);
+        $reflection = new ReflectionClass(OtpService::class);
         $constructor = $reflection->getConstructor();
+        $this->assertInstanceOf(ReflectionMethod::class, $constructor);
         $parameters = $constructor->getParameters();

         // Assert: The constructor expects CodeGeneratorInterface and RateLimiterInterface
-        $this->assertInstanceOf(\ReflectionParameter::class, $parameters[0]);
+        $this->assertInstanceOf(ReflectionParameter::class, $parameters[0]);
         $this->assertEquals(CodeGeneratorInterface::class, $parameters[0]->getType()->getName());
         $this->assertEquals(RateLimiterInterface::class, $parameters[1]->getType()->getName());
     }
@@ Line 402 @@
         // Act: Register the service provider and resolve TOTPService
         $provider->register();

-        $reflection = new \ReflectionClass(TOTPService::class);
+        $reflection = new ReflectionClass(TOTPService::class);
         $constructor = $reflection->getConstructor();
+        $this->assertInstanceOf(ReflectionMethod::class, $constructor);
         $parameters = $constructor->getParameters();

         // Assert: The constructor expects period, digits, algorithm, window parameters
-        $this->assertInstanceOf(\ReflectionParameter::class, $parameters[0]);
-        $this->assertEquals('period', $parameters[0]->getName());
-        $this->assertEquals('digits', $parameters[1]->getName());
-        $this->assertEquals('algorithm', $parameters[2]->getName());
-        $this->assertEquals('window', $parameters[3]->getName());
+        $this->assertInstanceOf(ReflectionParameter::class, $parameters[0]);
+        $this->assertSame('period', $parameters[0]->getName());
+        $this->assertSame('digits', $parameters[1]->getName());
+        $this->assertSame('algorithm', $parameters[2]->getName());
+        $this->assertSame('window', $parameters[3]->getName());
     }

     /**
@@ Line 473 @@

         // Verify command signature
         $command = new CleanupMfaCommand();
-        $this->assertEquals('mfa:cleanup', $command->getName(), 'Command should have correct signature');
+        $this->assertSame('mfa:cleanup', $command->getName(), 'Command should have correct signature');

         // Verify the command is registered via Artisan
-        $artisan = $this->app->make('Illuminate\Contracts\Console\Kernel');
+        $artisan = $this->app->make(Kernel::class);
         $commands = $artisan->all();
         $this->assertArrayHasKey('mfa:cleanup', $commands, 'Command should be registered with Artisan');
     }
    ----------- end diff -----------

Applied rules:
 * AddInstanceofAssertForNullableInstanceRector
 * AssertEqualsToSameRector
 * StringClassNameToClassConstantRector


9) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Notifications/OtpNotificationTest.php:24

    ---------- begin diff ----------
@@ Line 24 @@
     use RefreshDatabase;

     private OneTimePassword $otp;
+
     private string $plainCode;
+
     private TestUser $testUser;

     /**
@@ Line 74 @@
         $channels = $notification->via($this->testUser);

         // Assert: OTP channels are used
-        $this->assertEquals($customChannels, $channels);
+        $this->assertSame($customChannels, $channels);
     }

     /**
@@ Line 91 @@
         $channels = $notification->via($this->testUser);

         // Assert: OTP channels take precedence over notifiable channels
-        $this->assertEquals($otpChannels, $channels);
+        $this->assertSame($otpChannels, $channels);
         $this->assertNotEquals($this->testUser->getOtpChannels(), $channels);
     }

@@ Line 144 @@
         $channels = $notification->via($plainNotifiable);

         // Assert: Default mail channel is used
-        $this->assertEquals(['mail'], $channels);
+        $this->assertSame(['mail'], $channels);
     }

     /**
@@ Line 307 @@
         $channels = $notification->via($this->testUser);

         // Assert: OTP channels take priority over notifiable's getOtpChannels()
-        $this->assertEquals($otpChannels, $channels);
-        $this->assertNotEquals(['mail'], $channels);
+        $this->assertSame($otpChannels, $channels);
+        $this->assertNotSame(['mail'], $channels);
     }

     /**
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * AssertEqualsToSameRector


10) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Services/DefaultCodeGeneratorTest.php:39

    ---------- begin diff ----------
@@ Line 39 @@
         $code = $this->generator->generate();

         // Assert: Code is 6 characters long
-        $this->assertEquals(6, strlen($code));
+        $this->assertSame(6, strlen($code));
     }

     /**
@@ Line 62 @@
         // Note: We cannot guarantee a small number, but we can test the format
         // Act: Generate multiple codes to observe possible leading zeros
         $codes = [];
-        for ($i = 0; $i < 100; $i++) {
+        for ($i = 0; $i < 100; ++$i) {
             $codes[] = $this->generator->generate();
         }

         // Assert: All codes are 6 digits
         foreach ($codes as $code) {
-            $this->assertEquals(6, strlen($code));
+            $this->assertSame(6, strlen($code));
             $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
         }
     }
@@ Line 93 @@
     public function test_generate_produces_different_codes_on_successive_calls(): void
     {
         // Act: Generate two codes
-        $code1 = $this->generator->generate();
-        $code2 = $this->generator->generate();
+        $this->generator->generate();
+        $this->generator->generate();

         // Note: There is a very small chance they could be equal (1/1,000,000)
         // This test might occasionally fail, but it's extremely unlikely
         // For deterministic tests, we generate multiple codes
         $codes = [];
-        for ($i = 0; $i < 10; $i++) {
+        for ($i = 0; $i < 10; ++$i) {
             $codes[] = $this->generator->generate();
         }

@@ Line 128 @@
     {
         // Act: Generate 1000 codes
         $codes = [];
-        for ($i = 0; $i < 1000; $i++) {
+        for ($i = 0; $i < 1000; ++$i) {
             $codes[] = $this->generator->generate();
         }

         // Assert: All codes are valid 6-digit strings
         foreach ($codes as $code) {
-            $this->assertEquals(6, strlen($code));
+            $this->assertSame(6, strlen($code));
             $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
         }

@@ Line 161 @@
     public function test_all_generated_codes_are_exactly_six_digits(): void
     {
         // Act: Generate 100 codes
-        for ($i = 0; $i < 100; $i++) {
+        for ($i = 0; $i < 100; ++$i) {
             $code = $this->generator->generate();

             // Assert: Each code is exactly 6 digits
-            $this->assertEquals(6, strlen($code), "Code '{$code}' should be 6 digits");
+            $this->assertSame(6, strlen($code), sprintf("Code '%s' should be 6 digits", $code));
             $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
         }
     }
@@ Line 184 @@
         $hasLeadingZero = false;
         $hasNoLeadingZero = false;

-        for ($i = 0; $i < 1000; $i++) {
+        for ($i = 0; $i < 1000; ++$i) {
             $code = $generator->generate();
             if ($code[0] === '0') {
                 $hasLeadingZero = true;
@@ Line 210 @@
     {
         // Act: Generate 10000 codes
         $codes = [];
-        for ($i = 0; $i < 10000; $i++) {
+        for ($i = 0; $i < 10000; ++$i) {
             $codes[] = $this->generator->generate();
         }

@@ Line 218 @@
         $firstDigitCounts = array_fill(0, 10, 0);
         foreach ($codes as $code) {
             $firstDigit = (int) $code[0];
-            $firstDigitCounts[$firstDigit]++;
+            ++$firstDigitCounts[$firstDigit];
         }

         // Assert: Each first digit appears between 8% and 12% (800-1200 out of 10000)
         foreach ($firstDigitCounts as $digit => $count) {
-            $this->assertGreaterThan(800, $count, "Digit {$digit} appears only {$count} times");
-            $this->assertLessThan(1200, $count, "Digit {$digit} appears {$count} times (too many)");
+            $this->assertGreaterThan(800, $count, sprintf('Digit %d appears only %d times', $digit, $count));
+            $this->assertLessThan(1200, $count, sprintf('Digit %d appears %d times (too many)', $digit, $count));
         }
     }
 }
    ----------- end diff -----------

Applied rules:
 * EncapsedStringsToSprintfRector
 * PostIncDecToPreIncDecRector
 * RemoveUnusedVariableAssignRector
 * AssertEqualsToSameRector


11) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Services/LaravelRateLimiterTest.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Tests\Unit\Services;

-use Illuminate\Support\Facades\RateLimiter;
 use Kani\Mfa\Otp\Services\LaravelRateLimiter;
 use Kani\Mfa\Tests\TestCase;

@@ Line 19 @@
 final class LaravelRateLimiterTest extends TestCase
 {
     private LaravelRateLimiter $rateLimiter;
+
     private string $testKey;

     /**
@@ Line 51 @@
     public function test_is_exceeded_returns_true_when_limit_reached(): void
     {
         // Arrange: Hit the rate limiter 3 times (reaching the limit)
-        for ($i = 0; $i < 3; $i++) {
+        for ($i = 0; $i < 3; ++$i) {
             $this->rateLimiter->hit($this->testKey, 60);
         }

@@ Line 68 @@
     public function test_is_exceeded_returns_false_after_clear(): void
     {
         // Arrange: Hit the rate limiter 3 times (reaching the limit)
-        for ($i = 0; $i < 3; $i++) {
+        for ($i = 0; $i < 3; ++$i) {
             $this->rateLimiter->hit($this->testKey, 60);
         }

@@ Line 132 @@
         $availableIn = $this->rateLimiter->getAvailableInSeconds($this->testKey);

         // Assert: Should be 0 because no limit is set
-        $this->assertEquals(0, $availableIn);
+        $this->assertSame(0, $availableIn);
     }

     /**
@@ Line 141 @@
     public function test_clear_removes_rate_limit_records(): void
     {
         // Arrange: Create a rate limit by hitting 3 times
-        for ($i = 0; $i < 3; $i++) {
+        for ($i = 0; $i < 3; ++$i) {
             $this->rateLimiter->hit($this->testKey, 60);
         }

@@ Line 155 @@
         $this->assertFalse($this->rateLimiter->isExceeded($this->testKey, 3));

         // Assert: Available time should be 0
-        $this->assertEquals(0, $this->rateLimiter->getAvailableInSeconds($this->testKey));
+        $this->assertSame(0, $this->rateLimiter->getAvailableInSeconds($this->testKey));
     }

     /**
@@ Line 168 @@
         $key2 = 'key2_' . uniqid();

         // Act: Hit only key1
-        for ($i = 0; $i < 3; $i++) {
+        for ($i = 0; $i < 3; ++$i) {
             $this->rateLimiter->hit($key1, 60);
         }

@@ Line 222 @@
         $availableIn = $this->rateLimiter->getAvailableInSeconds($nonExistentKey);

         // Assert: Should be 0
-        $this->assertEquals(0, $availableIn);
+        $this->assertSame(0, $availableIn);
     }

     /**
@@ Line 231 @@
     public function test_multiple_hits_accumulate_correctly(): void
     {
         // Arrange: Hit 5 times with max attempts 3
-        for ($i = 0; $i < 5; $i++) {
+        for ($i = 0; $i < 5; ++$i) {
             $this->rateLimiter->hit($this->testKey, 60);
         }
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * PostIncDecToPreIncDecRector
 * AssertEqualsToSameRector


12) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Services/MfaInstallerServiceTest.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Tests\Unit\Services;

+use ReflectionClass;
 use Illuminate\Console\Command;
 use Illuminate\Support\Facades\File;
 use Illuminate\Support\Facades\Schema;
@@ Line 21 @@
 final class MfaInstallerServiceTest extends TestCase
 {
     private MfaInstallerService $installerService;
+
     private Command $command;
+
     private string $configPath;

     protected function setUp(): void
@@ Line 522 @@
             });
         }

-        $reflection = new \ReflectionClass($this->installerService);
+        $reflection = new ReflectionClass($this->installerService);
         $method = $reflection->getMethod('hasOtpTables');
         $method->setAccessible(true);

@@ Line 541 @@
         // Arrange: Ensure OTP table does not exist
         $this->dropTableIfExists('one_time_passwords');

-        $reflection = new \ReflectionClass($this->installerService);
+        $reflection = new ReflectionClass($this->installerService);
         $method = $reflection->getMethod('hasOtpTables');
         $method->setAccessible(true);

@@ Line 568 @@
             });
         }

-        $reflection = new \ReflectionClass($this->installerService);
+        $reflection = new ReflectionClass($this->installerService);
         $method = $reflection->getMethod('hasTotpTables');
         $method->setAccessible(true);

@@ Line 589 @@
         // Arrange: Ensure TOTP table does not exist
         $this->dropTableIfExists('two_factor_secrets');

-        $reflection = new \ReflectionClass($this->installerService);
+        $reflection = new ReflectionClass($this->installerService);
         $method = $reflection->getMethod('hasTotpTables');
         $method->setAccessible(true);
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector


13) /home/andy-kani/pro/sites/packages/laravel-otp/src/Otp/Notifications/OtpNotification.php:94

    ---------- begin diff ----------
@@ Line 94 @@
     {
         $channels = $this->otp->channels;

-        if ($channels === null || !is_array($channels) || empty($channels)) {
+        if ($channels === null || !is_array($channels) || $channels === []) {
             return null;
         }
    ----------- end diff -----------

Applied rules:
 * SimplifyEmptyCheckOnEmptyArrayRector


14) /home/andy-kani/pro/sites/packages/laravel-otp/src/Otp/Services/OtpService.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Otp\Services;

+use Exception;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Support\Facades\Hash;
 use Illuminate\Support\Facades\Log;
@@ Line 32 @@
      * @param int $defaultMaxAttempts Default maximum verification attempts
      * @param int $rateLimitRequests Maximum requests per time window
      * @param int $rateLimitVerifications Maximum verifications per time window
-     * @param int $rateLimitDecayMinutes Rate limit window duration in minutes
      * @param int $failedVerificationDecaySeconds Decay time for failed verifications
      * @param int $rateLimitHitDecaySeconds Decay time for rate limit hits
      */
@@ Line 43 @@
         private readonly int $defaultMaxAttempts = 3,
         private readonly int $rateLimitRequests = 3,
         private readonly int $rateLimitVerifications = 5,
-        private readonly int $rateLimitDecayMinutes = 60,
         private readonly int $failedVerificationDecaySeconds = 300,
         private readonly int $rateLimitHitDecaySeconds = 60
     ) {}
@@ Line 137 @@
     ): OtpResponseData {
         $pendingOtp = $this->findPendingOtp($otpable, $type, $destination);

-        if (!$pendingOtp) {
+        if (!$pendingOtp instanceof OneTimePassword) {
             return $this->send($otpable, $type, $destination, $channels, $metadata, $expiresInMinutes, $maxAttempts);
         }

@@ Line 209 @@
         // Find OTP without expiration filter first
         $otpRecord = $this->findOtpForVerification($otpable, $type, $destination);

-        if (!$otpRecord) {
+        if (!$otpRecord instanceof OneTimePassword) {
             $this->recordFailedVerificationAttempt($rateLimitKey);
             return OtpResponseData::notFound(TranslationHelper::trans('messages.otp_not_found'));
         }
@@ Line 444 @@
         try {
             $otpable->notify(new OtpNotification($otpRecord, $plainCode));
             return true;
-        } catch (\Exception $exception) {
+        } catch (Exception $exception) {
             Log::error('Failed to send OTP notification', [
                 'otpable_type' => $otpable->getMorphClass(),
                 'otpable_id' => $otpable->getKey(),
@@ Line 454 @@
             ]);
             return false;
         }
-    }
-
-    /**
-     * Find any OTP record (valid or invalid) for the given parameters.
-     *
-     * @param Model $otpable The entity
-     * @param string $type OTP type
-     * @param string $destination Destination address
-     * @return OneTimePassword|null The most recent OTP or null
-     */
-    private function findOtp(Model $otpable, string $type, string $destination): ?OneTimePassword
-    {
-        return OneTimePassword::where('otpable_type', $otpable->getMorphClass())
-            ->where('otpable_id', $otpable->getKey())
-            ->where('type', $type)
-            ->where('destination', $destination)
-            ->whereNull('cancelled_at')
-            ->latest()
-            ->first();
-    }
-
-    /**
-     * Find a valid OTP that is not expired, verified, used, or cancelled.
-     *
-     * @param Model $otpable The entity
-     * @param string $type OTP type
-     * @param string $destination Destination address
-     * @return OneTimePassword|null The valid OTP or null
-     */
-    private function findValidOtp(Model $otpable, string $type, string $destination): ?OneTimePassword
-    {
-        return OneTimePassword::where('otpable_type', $otpable->getMorphClass())
-            ->where('otpable_id', $otpable->getKey())
-            ->where('type', $type)
-            ->where('destination', $destination)
-            ->whereNull('verified_at')
-            ->whereNull('used_at')
-            ->whereNull('cancelled_at')
-            ->where('expires_at', '>', now())
-            ->latest()
-            ->first();
     }

     /**
    ----------- end diff -----------

Applied rules:
 * FlipTypeControlToUseExclusiveTypeRector
 * NullableCompareToNullRector
 * RemoveUnusedPrivateMethodRector
 * RemoveUnusedPromotedPropertyRector


15) /home/andy-kani/pro/sites/packages/laravel-otp/src/Otp/Traits/HasOneTimePasswords.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Otp\Traits;

+use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\MorphMany;
 use Kani\Mfa\Otp\Data\OtpResponseData;
 use Kani\Mfa\Otp\Models\OneTimePassword;
@@ Line 17 @@
  * ability to generate and verify OTPs with automatic rate limiting and
  * security features.
  *
- * @mixin \Illuminate\Database\Eloquent\Model
+ * @mixin Model
  */
 trait HasOneTimePasswords
 {
    ----------- end diff -----------

Applied rules:


16) /home/andy-kani/pro/sites/packages/laravel-otp/src/Totp/Models/TwoFactorSecret.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Totp\Models;

+use Carbon\CarbonImmutable;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\MorphTo;
 use Kani\Mfa\Totp\Services\TOTPService;
@@ Line 24 @@
  * @property array|null $recovery_codes
  * @property array|null $meta
  * @property bool $is_enabled
- * @property \Carbon\CarbonImmutable|null $confirmed_at
- * @property \Carbon\CarbonImmutable|null $last_used_at
- * @property \Carbon\CarbonImmutable $created_at
- * @property \Carbon\CarbonImmutable $updated_at
+ * @property CarbonImmutable|null $confirmed_at
+ * @property CarbonImmutable|null $last_used_at
+ * @property CarbonImmutable $created_at
+ * @property CarbonImmutable $updated_at
  */
 class TwoFactorSecret extends Model
 {
@@ Line 73 @@

     /**
      * Get the parent authenticatable model (polymorphic relationship).
-     *
-     * @return MorphTo
      */
     public function authenticatable(): MorphTo
     {
@@ Line 195 @@
         $plainTextCodes = [];
         $hashedCodes = [];

-        for ($i = 0; $i < $count; $i++) {
+        for ($i = 0; $i < $count; ++$i) {
             $plainCode = $this->generateSingleRecoveryCode($length);
             $plainTextCodes[] = $plainCode;
             $hashedCodes[] = $this->hashRecoveryCode($plainCode);
@@ Line 241 @@
         $charactersLength = strlen($characters);
         $code = '';

-        for ($i = 0; $i < $length; $i++) {
+        for ($i = 0; $i < $length; ++$i) {
             $randomIndex = random_int(0, $charactersLength - 1);
             $code .= $characters[$randomIndex];
         }
    ----------- end diff -----------

Applied rules:
 * PostIncDecToPreIncDecRector
 * RemoveUselessReturnTagRector


17) /home/andy-kani/pro/sites/packages/laravel-otp/src/Totp/Services/TOTPService.php:5

    ---------- begin diff ----------
@@ Line 5 @@

 namespace Kani\Mfa\Totp\Services;

+use Carbon\Carbon;
 use OTPHP\TOTP as OTPHPTOTP;
 use ParagonIE\ConstantTime\Base32;

@@ Line 50 @@
      * Create an OTPHP TOTP instance.
      *
      * @param string $secret The shared secret
-     * @return OTPHPTOTP
      */
     private function createTOTP(string $secret): OTPHPTOTP
     {
@@ Line 79 @@
         $effectiveWindow = $window ?? $this->window;

         // Use provided timestamp or current time
-        $verificationTime = $timestamp ?? time();
+        $verificationTime = $timestamp ?? Carbon::now()
+            ->getTimestamp();

         // For OTPHP library, we need to calculate the time window manually
         // because the library's verify method with timestamp and window parameters
@@ Line 88 @@
         $currentPeriod = floor($verificationTime / $this->period);

         // Check current period and +/- window periods
-        for ($offset = -$effectiveWindow; $offset <= $effectiveWindow; $offset++) {
+        for ($offset = -$effectiveWindow; $offset <= $effectiveWindow; ++$offset) {
             $period = $currentPeriod + $offset;
             $timestampForPeriod = (int) $period * $this->period;
    ----------- end diff -----------

Applied rules:
 * TimeFuncCallToCarbonRector
 * PostIncDecToPreIncDecRector
 * RemoveUselessReturnTagRector


18) /home/andy-kani/pro/sites/packages/laravel-otp/src/Totp/Traits/HasTwoFactorAuthentication.php:5

    ---------- begin diff ----------
@@ Line 5 @@

 namespace Kani\Mfa\Totp\Traits;

+use Illuminate\Database\Eloquent\Model;
+use Illuminate\Database\Eloquent\Relations\MorphOne;
 use Kani\Mfa\Totp\Models\TwoFactorSecret;
 use Kani\Mfa\Totp\Services\TOTPService;

@@ Line 17 @@
  * - Recovery code generation and verification
  * - Automatic handling of the polymorphic relationship
  *
- * @mixin \Illuminate\Database\Eloquent\Model
+ * @mixin Model
  */
 trait HasTwoFactorAuthentication
 {
@@ Line 24 @@
     /**
      * Define the polymorphic relationship with the two-factor secret.
      *
-     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
+     * @return MorphOne
      */
     public function twoFactorSecret()
     {
@@ Line 35 @@
      * Get or create the TOTP secret for this model.
      *
      * If no secret exists, a new one is generated automatically.
-     *
-     * @return TwoFactorSecret
      */
     public function getTwoFactorSecret(): TwoFactorSecret
     {
@@ Line 67 @@

     /**
      * Check if two-factor authentication is enabled for this model.
-     *
-     * @return bool
      */
     public function isTwoFactorEnabled(): bool
     {
@@ Line 136 @@
             $secret->update(['last_used_at' => now()]);
             return true;
         }
-
         // Verify recovery code
-        if ($secret->verifyRecoveryCode($code)) {
-            return true;
-        }
-
-        return false;
+        return (bool) $secret->verifyRecoveryCode($code);
     }

     /**
@@ Line 150 @@
      *
      * This URI can be used to generate a QR code that the user scans
      * with Google Authenticator or any compatible app.
-     *
-     * @return string
      */
     public function getTwoFactorQrCodeUri(): string
     {
@@ Line 163 @@
      *
      * This should be used after enabling 2FA or when the user requests
      * new recovery codes. Returns the plain text codes (show once).
-     *
-     * @return array
      */
     public function generateRecoveryCodes(): array
     {
@@ Line 173 @@

     /**
      * Get the hashed recovery codes (for debugging only).
-     *
-     * @return array
      */
     public function getRecoveryCodes(): array
     {
    ----------- end diff -----------

Applied rules:
 * SimplifyIfReturnBoolRector
 * RemoveUselessReturnTagRector


19) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Models/OneTimePasswordTest.php:24

    ---------- begin diff ----------
@@ Line 24 @@
     use RefreshDatabase;

     private array $validAttributes;
+
     private string $plainCode;

     /**
@@ Line 295 @@
         $otp = OneTimePassword::create($this->validAttributes);

         // Act
-        for ($i = 0; $i < 3; $i++) {
+        for ($i = 0; $i < 3; ++$i) {
             $otp->incrementAttempts();
         }
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * PostIncDecToPreIncDecRector


20) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Models/TwoFactorSecretTest.php:5

    ---------- begin diff ----------
@@ Line 5 @@

 namespace Kani\Mfa\Tests\Feature\Models;

+use Carbon\CarbonInterface;
+use Mockery;
 use Carbon\CarbonImmutable;
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Kani\Mfa\Tests\TestCase;
@@ Line 26 @@
     use RefreshDatabase;

     private TestUser $testUser;
+
     private TwoFactorSecret $twoFactorSecret;
+
     private string $testSecret;

     /**
@@ Line 110 @@

         // confirmed_at and last_used_at can be null initially
         $this->assertTrue($this->twoFactorSecret->confirmed_at === null ||
-            $this->twoFactorSecret->confirmed_at instanceof \Carbon\CarbonInterface);
+            $this->twoFactorSecret->confirmed_at instanceof CarbonInterface);
         $this->assertTrue($this->twoFactorSecret->last_used_at === null ||
-            $this->twoFactorSecret->last_used_at instanceof \Carbon\CarbonInterface);
+            $this->twoFactorSecret->last_used_at instanceof CarbonInterface);

         // created_at and updated_at are Carbon instances by default in Laravel
         // They can be Carbon or CarbonImmutable depending on configuration
-        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $this->twoFactorSecret->created_at);
-        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $this->twoFactorSecret->updated_at);
+        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->created_at);
+        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->updated_at);

         // recovery_codes can be null or array
         $this->assertTrue($this->twoFactorSecret->recovery_codes === null ||
@@ Line 157 @@

         // Assert
         $this->assertTrue($this->twoFactorSecret->isEnabled());
-        $this->assertNotNull($this->twoFactorSecret->confirmed_at);
+        $this->assertInstanceOf(CarbonImmutable::class, $this->twoFactorSecret->confirmed_at);
         // Use CarbonInterface instead of CarbonImmutable to support both Carbon and CarbonImmutable
-        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $this->twoFactorSecret->confirmed_at);
+        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->confirmed_at);
     }

     /**
@@ Line 210 @@
         $uri = $secret->getProvisioningUri();

         // Assert
-        $this->assertStringContainsString('issuer=' . rawurlencode(config('app.name')), $uri);
-        $this->assertStringContainsString((string) $this->testUser->getKey(), $uri);
+        $this->assertStringContainsString('issuer=' . rawurlencode(config('app.name')), (string) $uri);
+        $this->assertStringContainsString((string) $this->testUser->getKey(), (string) $uri);
     }

     /**
@@ Line 220 @@
     public function test_verify_code_returns_true_for_valid_code(): void
     {
         // Arrange: Mock the TOTPService to return true
-        $mockService = \Mockery::mock(TOTPService::class);
+        $mockService = Mockery::mock(TOTPService::class);
         $mockService->shouldReceive('verify')
             ->once()
             ->with($this->testSecret, '123456', 1)
@@ Line 237 @@
     public function test_verify_code_returns_false_for_invalid_code(): void
     {
         // Arrange: Mock the TOTPService to return false
-        $mockService = \Mockery::mock(TOTPService::class);
+        $mockService = Mockery::mock(TOTPService::class);
         $mockService->shouldReceive('verify')
             ->once()
             ->with($this->testSecret, '999999', 1)
@@ Line 465 @@
     public function test_confirmed_at_is_set_when_enabling(): void
     {
         // Arrange
-        $this->assertNull($this->twoFactorSecret->confirmed_at);
+        $this->assertNotInstanceOf(CarbonImmutable::class, $this->twoFactorSecret->confirmed_at);

         // Act
         $this->twoFactorSecret->enable();

         // Assert
-        $this->assertNotNull($this->twoFactorSecret->confirmed_at);
-        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $this->twoFactorSecret->confirmed_at);
+        $this->assertInstanceOf(CarbonImmutable::class, $this->twoFactorSecret->confirmed_at);
+        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->confirmed_at);
     }

     /**
@@ Line 481 @@
     public function test_last_used_at_can_be_updated(): void
     {
         // Arrange
-        $this->assertNull($this->twoFactorSecret->last_used_at);
+        $this->assertNotInstanceOf(CarbonImmutable::class, $this->twoFactorSecret->last_used_at);

         // Act
         $this->twoFactorSecret->touchLastUsedAt();

         // Assert
-        $this->assertNotNull($this->twoFactorSecret->refresh()->last_used_at);
-        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $this->twoFactorSecret->last_used_at);
+        $this->assertInstanceOf(CarbonImmutable::class, $this->twoFactorSecret->refresh()->last_used_at);
+        $this->assertInstanceOf(CarbonInterface::class, $this->twoFactorSecret->last_used_at);
     }
 }
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * AssertEmptyNullableObjectToAssertInstanceofRector
 * StringCastAssertStringContainsStringRector


21) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Services/OtpServiceTest.php:28

    ---------- begin diff ----------
@@ Line 28 @@
     use RefreshDatabase;

     private OtpService $otpService;
+
     private TestUser $testUser;
+
     private string $testType;
+
     private string $testDestination;

     /**
@@ Line 86 @@

         // Assert: Response indicates success
         $this->assertTrue($response->isSuccess());
-        $this->assertEquals(TranslationHelper::trans('messages.send_success'), $response->message);
+        $this->assertSame(TranslationHelper::trans('messages.send_success'), $response->message);

         // Assert: OTP record exists in database
         $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();
@@ Line 211 @@

         // Assert: Response is successful with correct message
         $this->assertTrue($response->isSuccess());
-        $this->assertEquals(TranslationHelper::trans('messages.resend_success'), $response->message);
+        $this->assertSame(TranslationHelper::trans('messages.resend_success'), $response->message);

         // Assert: First OTP was cancelled
         $firstOtp->refresh();
@@ Line 300 @@
         $otp = OneTimePassword::where('otpable_id', $this->testUser->id)->first();

         // Act: Perform 3 failed verification attempts
-        for ($attemptNumber = 0; $attemptNumber < 3; $attemptNumber++) {
+        for ($attemptNumber = 0; $attemptNumber < 3; ++$attemptNumber) {
             $this->otpService->verify($this->testUser, '000000', $this->testType, $this->testDestination);
         }

@@ Line 344 @@
         // Assert: Expired code response returned
         $this->assertFalse($response->isSuccess());
         $this->assertEquals('expired_code', $response->status->value);
-        $this->assertEquals(
+        $this->assertSame(
             TranslationHelper::trans('messages.expired_code'),
             $response->message
         );
@@ Line 365 @@

         // Assert: OTP was cancelled
         $this->assertTrue($response->isSuccess());
-        $this->assertEquals(TranslationHelper::trans('messages.cancel_success', ['count' => 1]), $response->message);
+        $this->assertSame(TranslationHelper::trans('messages.cancel_success', ['count' => 1]), $response->message);

         $otp->refresh();
         $this->assertNotNull($otp->cancelled_at);
@@ Line 381 @@

         // Assert: Success but with zero cancellations
         $this->assertTrue($response->isSuccess());
-        $this->assertEquals(TranslationHelper::trans('messages.no_pending_to_cancel'), $response->message);
+        $this->assertSame(TranslationHelper::trans('messages.no_pending_to_cancel'), $response->message);
         $this->assertEquals(0, $response->data['cancelled_count']);
     }
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * PostIncDecToPreIncDecRector
 * AssertEqualsToSameRector


22) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Services/TOTPServiceTest.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Tests\Feature\Services;

+use Exception;
+use OTPHP\Exception\SecretDecodingException;
+use ReflectionClass;
 use Kani\Mfa\Tests\TestCase;
 use Kani\Mfa\Totp\Services\TOTPService;
 use ParagonIE\ConstantTime\Base32;
-use OTPHP\TOTP as OTPHPTOTP;

 /**
  * Test suite for TOTPService core functionality.
@@ Line 20 @@
 final class TOTPServiceTest extends TestCase
 {
     private TOTPService $totpService;
+
     private string $testSecret;

     /**
@@ Line 61 @@
         try {
             $decoded = Base32::decodeUpper($secret);
             $this->assertIsString($decoded);
-            $this->assertEquals(20, strlen($decoded)); // 20 bytes = 160 bits
-        } catch (\Exception $e) {
-            $this->fail('Secret should be valid Base32: ' . $e->getMessage());
+            $this->assertSame(20, strlen($decoded)); // 20 bytes = 160 bits
+        } catch (Exception $exception) {
+            $this->fail('Secret should be valid Base32: ' . $exception->getMessage());
         }
     }

@@ Line 80 @@
         $secret3 = $this->totpService->generateSecret();

         // Assert: All secrets are different
-        $this->assertNotEquals($secret1, $secret2);
-        $this->assertNotEquals($secret1, $secret3);
-        $this->assertNotEquals($secret2, $secret3);
+        $this->assertNotSame($secret1, $secret2);
+        $this->assertNotSame($secret1, $secret3);
+        $this->assertNotSame($secret2, $secret3);
     }

     /**
@@ Line 96 @@
         $secret = $this->totpService->generateSecret();

         // Assert: Secret length should be 32 characters (20 bytes encoded in Base32)
-        $this->assertEquals(32, strlen($secret));
+        $this->assertSame(32, strlen($secret));
     }

     /**
@@ Line 111 @@

         // Assert: Code is 6 digits
         $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
-        $this->assertEquals(6, strlen($code));
+        $this->assertSame(6, strlen($code));
     }

     /**
@@ Line 252 @@
         $this->assertTrue($isValid);

         // Assert: Code has 8 digits
-        $this->assertEquals(8, strlen($code));
+        $this->assertSame(8, strlen($code));
     }

     /**
@@ Line 328 @@
         $code = '123456';

         // Expect an exception to be thrown
-        $this->expectException(\OTPHP\Exception\SecretDecodingException::class);
+        $this->expectException(SecretDecodingException::class);

         // Act: Try to verify with an invalid Base32 secret
         $this->totpService->verify($invalidSecret, $code);
@@ Line 348 @@

         // Assert: Default parameters work correctly
         $this->assertTrue($service->verify($secret, $code));
-        $this->assertEquals(6, strlen($code));
+        $this->assertSame(6, strlen($code));
         $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
     }

@@ Line 390 @@
         $code8Digits = $service8Digits->now($secret);

         // Assert: Correct digit lengths
-        $this->assertEquals(6, strlen($code6Digits));
-        $this->assertEquals(8, strlen($code8Digits));
+        $this->assertSame(6, strlen($code6Digits));
+        $this->assertSame(8, strlen($code8Digits));

         // Assert: Both verify correctly
         $this->assertTrue($service6Digits->verify($secret, $code6Digits));
@@ Line 417 @@
         $this->assertTrue($service60Sec->verify($secret, $code60Sec));

         // Note: Codes from different periods will be different
-        $this->assertNotEquals($code30Sec, $code60Sec);
+        $this->assertNotSame($code30Sec, $code60Sec);
     }

     /**
@@ Line 429 @@
         $service = new TOTPService(period: 45, digits: 7, algorithm: 'sha512', window: 3);

         // Act: Get properties via reflection
-        $reflection = new \ReflectionClass($service);
+        $reflection = new ReflectionClass($service);
         $periodProperty = $reflection->getProperty('period');
         $digitsProperty = $reflection->getProperty('digits');
         $algorithmProperty = $reflection->getProperty('algorithm');
    ----------- end diff -----------

Applied rules:
 * CatchExceptionNameMatchingTypeRector
 * NewlineBetweenClassLikeStmtsRector
 * AssertEqualsToSameRector


23) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Traits/HasOneTimePasswordsTest.php:24

    ---------- begin diff ----------
@@ Line 24 @@
     use RefreshDatabase;

     private TestUser $testUser;
+
     private string $testType;
+
     private string $testDestination;

     protected function setUp(): void
@@ Line 82 @@
         $otpServiceMock->shouldReceive('send')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $this->testType,
@@ Line 118 @@
         $otpServiceMock->shouldReceive('send')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $this->testType,
@@ Line 153 @@
         $otpServiceMock->shouldReceive('resend')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $this->testType,
@@ Line 187 @@
         $otpServiceMock->shouldReceive('verify')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $code,
@@ Line 229 @@
         $cancelledCount = $this->testUser->cancelOtps($this->testType, $this->testDestination);

         // Assert: Verify the OTP was cancelled
-        $this->assertEquals(1, $cancelledCount);
+        $this->assertSame(1, $cancelledCount);

         $otp->refresh();
         $this->assertNotNull($otp->cancelled_at);
@@ Line 241 @@
         $cancelledCount = $this->testUser->cancelOtps($this->testType, $this->testDestination);

         // Assert: Verify count is zero
-        $this->assertEquals(0, $cancelledCount);
+        $this->assertSame(0, $cancelledCount);
     }

     public function test_get_pending_otp_returns_valid_otp(): void
@@ Line 260 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify the correct OTP was found
-        $this->assertNotNull($foundOtp);
+        $this->assertInstanceOf(OneTimePassword::class, $foundOtp);
         $this->assertEquals($otp->id, $foundOtp->id);
     }

@@ Line 280 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_get_pending_otp_returns_null_for_used_otp(): void
@@ Line 300 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_get_pending_otp_returns_null_for_verified_otp(): void
@@ Line 320 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_get_pending_otp_returns_null_for_cancelled_otp(): void
@@ Line 340 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_has_valid_otp_returns_true_when_valid_otp_exists(): void
@@ Line 396 @@
         $deletedCount = $this->testUser->cleanupExpiredOtps();

         // Assert: Verify only expired OTP was deleted
-        $this->assertEquals(1, $deletedCount);
+        $this->assertSame(1, $deletedCount);
         $this->assertDatabaseMissing('one_time_passwords', ['id' => $expiredOtp->id]);
         $this->assertDatabaseHas('one_time_passwords', ['id' => $validOtp->id]);
     }
@@ Line 418 @@
         $deletedCount = $this->testUser->cleanupExpiredOtps();

         // Assert: Verified OTP should not be deleted
-        $this->assertEquals(0, $deletedCount);
+        $this->assertSame(0, $deletedCount);
         $this->assertDatabaseHas('one_time_passwords', ['id' => $expiredVerifiedOtp->id]);
     }
 }
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * AssertEmptyNullableObjectToAssertInstanceofRector
 * AssertEqualsToSameRector
 * ClosureReturnTypeRector


24) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Traits/HasTwoFactorAuthenticationTest.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Mfa\Tests\Feature\Traits;

+use Illuminate\Database\Eloquent\Relations\MorphOne;
+use Carbon\CarbonImmutable;
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Kani\Mfa\Tests\TestCase;
 use Kani\Mfa\Tests\Support\TestUser;
@@ Line 23 @@
     use RefreshDatabase;

     private TestUser $user;
+
     private TOTPService $totpService;
+
     private string $testSecret;

     /**
@@ Line 65 @@
         $relation = $this->user->twoFactorSecret();

         // Assert: Relation is morphOne
-        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphOne::class, $relation);
+        $this->assertInstanceOf(MorphOne::class, $relation);

         // Assert: Can retrieve the secret
         $retrievedSecret = $this->user->twoFactorSecret;
@@ Line 328 @@
         $this->user->enableTwoFactor($validCode);

         // Initially last_used_at should be null
-        $this->assertNull($secret->fresh()->last_used_at);
+        $this->assertNotInstanceOf(CarbonImmutable::class, $secret->fresh()->last_used_at);

         // Get a fresh valid code
         $currentValidCode = $this->totpService->now($secret->secret);
@@ Line 405 @@
     public function test_get_two_factor_qr_code_uri_returns_provisioning_uri(): void
     {
         // Arrange: Get secret
-        $secret = $this->user->getTwoFactorSecret();
+        $this->user->getTwoFactorSecret();
         $uri = $this->user->getTwoFactorQrCodeUri();

         // Assert: URI contains required components
@@ Line 615 @@
         $this->user->enableTwoFactor($validCode);

         // Act: Verify invalid codes multiple times
-        for ($i = 0; $i < 10; $i++) {
+        for ($i = 0; $i < 10; ++$i) {
             $result = $this->user->verifyTwoFactorCode('000000');
             $this->assertFalse($result);
         }
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * PostIncDecToPreIncDecRector
 * RemoveUnusedVariableAssignRector
 * AssertEmptyNullableObjectToAssertInstanceofRector


 [OK] 24 files would have been changed (dry-run) by Rector                                                              

