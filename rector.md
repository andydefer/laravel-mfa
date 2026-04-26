# Rector Refactoring Report
*Generated: dim. 26 avril 2026 10:33:56 WAT*


16 files with changes
=====================

1) /home/andy-kani/pro/sites/packages/laravel-otp/src/Commands/CleanupOtpsCommand.php:385

    ---------- begin diff ----------
@@ Line 385 @@
         $this->addStatisticRowIfNeeded($rows, 'Used OTPs deleted', $statistics['used'], $statistics['used'] > 0);
         $this->addStatisticRowIfNeeded($rows, 'Cancelled OTPs deleted', $statistics['cancelled'], $statistics['cancelled'] > 0);

-        if (count($rows) > 0) {
+        if ($rows !== []) {
             $rows[] = ['━━━━━━━━━━━━━━━━━━━━━', '━━━━━━━━━'];
             $rows[] = ['Total OTPs deleted', $statistics['total']];
         } else {
    ----------- end diff -----------

Applied rules:
 * CountArrayToEmptyArrayComparisonRector


2) /home/andy-kani/pro/sites/packages/laravel-otp/src/Helpers/TranslationHelper.php:25

    ---------- begin diff ----------
@@ Line 25 @@
     {
         $locale = self::getLocale();

-        return Lang::get("otp::{$key}", $replace, $locale);
+        return Lang::get('otp::' . $key, $replace, $locale);
     }

     /**
    ----------- end diff -----------

Applied rules:
 * EncapsedStringsToSprintfRector


3) /home/andy-kani/pro/sites/packages/laravel-otp/src/Models/OneTimePassword.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Otp\Models;

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


4) /home/andy-kani/pro/sites/packages/laravel-otp/src/Notifications/OtpNotification.php:94

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


5) /home/andy-kani/pro/sites/packages/laravel-otp/src/Services/OtpInstallerService.php:117

    ---------- begin diff ----------
@@ Line 117 @@
     private function isAlreadyInstalled(): bool
     {
         $configPath = config_path('otp.php');
-
-        return File::exists($configPath) || $this->hasCoreTables();
+        if (File::exists($configPath)) {
+            return true;
+        }
+        return $this->hasCoreTables();
     }

     /**
@@ Line 237 @@
             Artisan::call('migrate', ['--force' => true]);
             $command->info(Artisan::output());
             $command->info('   ✅ Migrations completed successfully.');
-        } catch (RuntimeException $exception) {
-            $command->error('   ❌ Migration failed: ' . $exception->getMessage());
+        } catch (RuntimeException $runtimeException) {
+            $command->error('   ❌ Migration failed: ' . $runtimeException->getMessage());
         }
     }

@@ Line 257 @@
                     return true;
                 }
             }
-        } catch (RuntimeException $exception) {
+        } catch (RuntimeException $runtimeException) {
             return false;
         }
    ----------- end diff -----------

Applied rules:
 * CatchExceptionNameMatchingTypeRector
 * ReturnBinaryOrToEarlyReturnRector


6) /home/andy-kani/pro/sites/packages/laravel-otp/src/Services/OtpService.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Otp\Services;

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


7) /home/andy-kani/pro/sites/packages/laravel-otp/src/Traits/HasOneTimePasswords.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Otp\Traits;

+use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\Relations\MorphMany;
 use Kani\Otp\Data\OtpResponseData;
 use Kani\Otp\Models\OneTimePassword;
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


8) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Models/OneTimePasswordTest.php:24

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


9) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Services/OtpServiceTest.php:28

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


10) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Feature/Traits/HasOneTimePasswordsTest.php:5

    ---------- begin diff ----------
@@ Line 5 @@

 namespace Kani\Otp\Tests\Feature\Traits;

+use Kani\Otp\Data\OtpResponseData;
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Kani\Otp\Models\OneTimePassword;
 use Kani\Otp\Services\OtpService;
@@ Line 23 @@
     use RefreshDatabase;

     private TestUser $testUser;
+
     private string $testType;
+
     private string $testDestination;

     protected function setUp(): void
@@ Line 81 @@
         $otpServiceMock->shouldReceive('send')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $this->testType,
@@ Line 91 @@
                 null,
                 null
             )
-            ->andReturn(\Kani\Otp\Data\OtpResponseData::success());
+            ->andReturn(OtpResponseData::success());

         $this->app->instance(OtpService::class, $otpServiceMock);

@@ Line 117 @@
         $otpServiceMock->shouldReceive('send')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $this->testType,
@@ Line 127 @@
                 $expiresInMinutes,
                 $maxAttempts
             )
-            ->andReturn(\Kani\Otp\Data\OtpResponseData::success());
+            ->andReturn(OtpResponseData::success());

         $this->app->instance(OtpService::class, $otpServiceMock);

@@ Line 152 @@
         $otpServiceMock->shouldReceive('resend')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $this->testType,
@@ Line 162 @@
                 null,
                 null
             )
-            ->andReturn(\Kani\Otp\Data\OtpResponseData::success());
+            ->andReturn(OtpResponseData::success());

         $this->app->instance(OtpService::class, $otpServiceMock);

@@ Line 186 @@
         $otpServiceMock->shouldReceive('verify')
             ->once()
             ->with(
-                Mockery::on(function ($argument) {
+                Mockery::on(function ($argument): bool {
                     return $argument instanceof TestUser && $argument->id === $this->testUser->id;
                 }),
                 $code,
@@ Line 194 @@
                 $this->testDestination,
                 $consume
             )
-            ->andReturn(\Kani\Otp\Data\OtpResponseData::success());
+            ->andReturn(OtpResponseData::success());

         $this->app->instance(OtpService::class, $otpServiceMock);

@@ Line 228 @@
         $cancelledCount = $this->testUser->cancelOtps($this->testType, $this->testDestination);

         // Assert: Verify the OTP was cancelled
-        $this->assertEquals(1, $cancelledCount);
+        $this->assertSame(1, $cancelledCount);

         $otp->refresh();
         $this->assertNotNull($otp->cancelled_at);
@@ Line 240 @@
         $cancelledCount = $this->testUser->cancelOtps($this->testType, $this->testDestination);

         // Assert: Verify count is zero
-        $this->assertEquals(0, $cancelledCount);
+        $this->assertSame(0, $cancelledCount);
     }

     public function test_get_pending_otp_returns_valid_otp(): void
@@ Line 259 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify the correct OTP was found
-        $this->assertNotNull($foundOtp);
+        $this->assertInstanceOf(OneTimePassword::class, $foundOtp);
         $this->assertEquals($otp->id, $foundOtp->id);
     }

@@ Line 279 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_get_pending_otp_returns_null_for_used_otp(): void
@@ Line 299 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_get_pending_otp_returns_null_for_verified_otp(): void
@@ Line 319 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_get_pending_otp_returns_null_for_cancelled_otp(): void
@@ Line 339 @@
         $foundOtp = $this->testUser->getPendingOtp($this->testType, $this->testDestination);

         // Assert: Verify no OTP was found
-        $this->assertNull($foundOtp);
+        $this->assertNotInstanceOf(OneTimePassword::class, $foundOtp);
     }

     public function test_has_valid_otp_returns_true_when_valid_otp_exists(): void
@@ Line 395 @@
         $deletedCount = $this->testUser->cleanupExpiredOtps();

         // Assert: Verify only expired OTP was deleted
-        $this->assertEquals(1, $deletedCount);
+        $this->assertSame(1, $deletedCount);
         $this->assertDatabaseMissing('one_time_passwords', ['id' => $expiredOtp->id]);
         $this->assertDatabaseHas('one_time_passwords', ['id' => $validOtp->id]);
     }
@@ Line 417 @@
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


11) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Support/TestCheckPoint.php:6

    ---------- begin diff ----------
@@ Line 6 @@

 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes;
-use Kani\Otp\Contracts\MustNemesis;
 use Kani\Otp\Contracts\MustOtpChannels;
-use Kani\Otp\Traits\HasNemesisTokens;
 use Kani\Otp\Traits\HasOneTimePasswords;

 /**
    ----------- end diff -----------

Applied rules:


12) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Commands/CleanupOtpsCommandTest.php:5

    ---------- begin diff ----------
@@ Line 5 @@

 namespace Kani\Otp\Tests\Unit\Commands;

+use Kani\Otp\Commands\CleanupOtpsCommand;
 use Illuminate\Foundation\Testing\RefreshDatabase;
 use Kani\Otp\Models\OneTimePassword;
 use Kani\Otp\Tests\TestCase;
@@ Line 27 @@
     public function test_command_can_be_instantiated(): void
     {
         // Act
-        $command = $this->app->make(\Kani\Otp\Commands\CleanupOtpsCommand::class);
+        $command = $this->app->make(CleanupOtpsCommand::class);

         // Assert
-        $this->assertInstanceOf(\Kani\Otp\Commands\CleanupOtpsCommand::class, $command);
+        $this->assertInstanceOf(CleanupOtpsCommand::class, $command);
     }

     /**
@@ Line 39 @@
     public function test_command_has_correct_signature(): void
     {
         // Act
-        $command = $this->app->make(\Kani\Otp\Commands\CleanupOtpsCommand::class);
+        $command = $this->app->make(CleanupOtpsCommand::class);

         // Assert
         $this->assertEquals('otp:cleanup', $command->getName());
    ----------- end diff -----------

Applied rules:


13) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Helpers/TranslationHelperTest.php:30

    ---------- begin diff ----------
@@ Line 30 @@
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
@@ Line 50 @@
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
@@ Line 69 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);

         // Assert: Falls back to English translation
-        $this->assertEquals('Your verification code - Test App', $subject);
+        $this->assertSame('Your verification code - Test App', $subject);
     }

     /**
@@ Line 86 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test App']);

         // Assert: Default English translation is used
-        $this->assertEquals('Your verification code - Test App', $subject);
+        $this->assertSame('Your verification code - Test App', $subject);
     }

     /**
@@ Line 103 @@
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
@@ Line 123 @@
         ]);

         // Assert: The placeholder is replaced correctly
-        $this->assertEquals('Invalid OTP code. You have 2 attempts remaining.', $message);
+        $this->assertSame('Invalid OTP code. You have 2 attempts remaining.', $message);
     }

     /**
@@ Line 138 @@
         $result = TranslationHelper::trans('messages.non_existent_key');

         // Assert: Returns the key itself (Laravel default behavior)
-        $this->assertEquals('otp::messages.non_existent_key', $result);
+        $this->assertSame('otp::messages.non_existent_key', $result);
     }

     /**
@@ Line 155 @@
         $translatedMessage = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

         // Assert: Package uses English but application locale remains French
-        $this->assertEquals('Your verification code - Test', $translatedMessage);
+        $this->assertSame('Your verification code - Test', $translatedMessage);
         $this->assertEquals('fr', app()->getLocale());
     }

@@ Line 173 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

         // Assert: Falls back to French instead
-        $this->assertEquals('Votre code de vérification - Test', $subject);
+        $this->assertSame('Votre code de vérification - Test', $subject);
     }

     /**
@@ Line 188 @@
         $ignoreMessage = TranslationHelper::trans('messages.ignore_request');

         // Assert: Returns the message as-is without errors
-        $this->assertEquals(
+        $this->assertSame(
             'If you did not request this verification, please ignore this email.',
             $ignoreMessage
         );
@@ Line 208 @@
         $subject = TranslationHelper::trans('messages.subject', ['app_name' => 'Test']);

         // Assert: Falls back to English default without errors
-        $this->assertEquals('Your verification code - Test', $subject);
+        $this->assertSame('Your verification code - Test', $subject);
     }
 }
    ----------- end diff -----------

Applied rules:
 * AssertEqualsToSameRector


14) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Notifications/OtpNotificationTest.php:24

    ---------- begin diff ----------
@@ Line 24 @@
     use RefreshDatabase;

     private OneTimePassword $otp;
+
     private string $plainCode;
+
     private TestUser $testUser;

     /**
@@ Line 73 @@
         $channels = $notification->via($this->testUser);

         // Assert: OTP channels are used
-        $this->assertEquals($customChannels, $channels);
+        $this->assertSame($customChannels, $channels);
     }

     /**
@@ Line 90 @@
         $channels = $notification->via($this->testUser);

         // Assert: OTP channels take precedence over notifiable channels
-        $this->assertEquals($otpChannels, $channels);
+        $this->assertSame($otpChannels, $channels);
         $this->assertNotEquals($this->testUser->getOtpChannels(), $channels);
     }

@@ Line 143 @@
         $channels = $notification->via($plainNotifiable);

         // Assert: Default mail channel is used
-        $this->assertEquals(['mail'], $channels);
+        $this->assertSame(['mail'], $channels);
     }

     /**
@@ Line 301 @@
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


15) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/OtpServiceProviderTest.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Otp\Tests\Unit;

+use ReflectionClass;
+use ReflectionMethod;
+use ReflectionParameter;
 use Kani\Otp\Commands\CleanupOtpsCommand;
 use Kani\Otp\Commands\InstallOtpCommand;
 use Kani\Otp\Contracts\CodeGeneratorInterface;
@@ Line 110 @@
         $provider->boot();

         // Assert: Translation helper should return English text
-        $this->assertEquals('Your verification code - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
-        $this->assertEquals('Hello %s!', TranslationHelper::trans('messages.greeting'));
-        $this->assertEquals('User', TranslationHelper::trans('messages.default_user_name'));
+        $this->assertSame('Your verification code - :app_name', TranslationHelper::trans('messages.subject', ['app_name' => ':app_name']));
+        $this->assertSame('Hello %s!', TranslationHelper::trans('messages.greeting'));
+        $this->assertSame('User', TranslationHelper::trans('messages.default_user_name'));
     }

     /**
@@ Line 129 @@
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
@@ Line 323 @@
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
    ----------- end diff -----------

Applied rules:
 * AddInstanceofAssertForNullableInstanceRector
 * AssertEqualsToSameRector


16) /home/andy-kani/pro/sites/packages/laravel-otp/tests/Unit/Services/OtpInstallerServiceTest.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Otp\Tests\Unit\Services;

+use ReflectionClass;
 use Illuminate\Console\Command;
 use Illuminate\Support\Facades\File;
 use Illuminate\Support\Facades\Schema;
@@ Line 14 @@
 final class OtpInstallerServiceTest extends TestCase
 {
     private OtpInstallerService $installerService;
+
     private Command $command;
+
     private string $configPath;
-    private string $tempMigrationsDir;

     protected function setUp(): void
     {
@@ Line 35 @@
         }

         // Nettoyer le dossier de migrations de destination
-        $this->tempMigrationsDir = database_path('migrations');
-        if (File::exists($this->tempMigrationsDir)) {
-            foreach (glob($this->tempMigrationsDir . '/*.php') as $file) {
+        $tempMigrationsDir = database_path('migrations');
+        if (File::exists($tempMigrationsDir)) {
+            foreach (glob($tempMigrationsDir . '/*.php') as $file) {
                 File::delete($file);
             }
         }
@@ Line 101 @@
     public function test_migrations_are_skipped_when_tables_already_exist(): void
     {
         if (!Schema::hasTable('one_time_passwords')) {
-            Schema::create('one_time_passwords', function ($table) {
+            Schema::create('one_time_passwords', function ($table): void {
                 $table->id();
             });
         }
@@ Line 126 @@
     public function test_has_core_tables_returns_true_when_tables_exist(): void
     {
         if (!Schema::hasTable('one_time_passwords')) {
-            Schema::create('one_time_passwords', function ($table) {
+            Schema::create('one_time_passwords', function ($table): void {
                 $table->id();
             });
         }

-        $reflection = new \ReflectionClass($this->installerService);
+        $reflection = new ReflectionClass($this->installerService);
         $method = $reflection->getMethod('hasCoreTables');
         $method->setAccessible(true);

@@ Line 146 @@
             Schema::drop('one_time_passwords');
         }

-        $reflection = new \ReflectionClass($this->installerService);
+        $reflection = new ReflectionClass($this->installerService);
         $method = $reflection->getMethod('hasCoreTables');
         $method->setAccessible(true);
    ----------- end diff -----------

Applied rules:
 * NewlineBetweenClassLikeStmtsRector
 * NarrowUnusedSetUpDefinedPropertyRector
 * AddClosureVoidReturnTypeWhereNoReturnRector


 [OK] 16 files would have been changed (dry-run) by Rector                                                              

