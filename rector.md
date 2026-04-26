# Rector Refactoring Report
*Generated: dim. 26 avril 2026 05:45:56 WAT*


6 files with changes
====================

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


2) /home/andy-kani/pro/sites/packages/laravel-otp/src/Models/OneTimePassword.php:62

    ---------- begin diff ----------
@@ Line 62 @@

     /**
      * Get the parent otpable model (polymorphic relationship).
-     *
-     * @return MorphTo
      */
     public function otpable(): MorphTo
     {
@@ Line 179 @@
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


3) /home/andy-kani/pro/sites/packages/laravel-otp/src/Notifications/OtpNotification.php:76

    ---------- begin diff ----------
@@ Line 76 @@
             ->line($plainCode)
             ->line('</div>')
             ->line('')
-            ->line("Ce code expirera dans {$expiresIn} minute(s).")
+            ->line(sprintf('Ce code expirera dans %s minute(s).', $expiresIn))
             ->line('Si vous n\'avez pas demandé cette vérification, veuillez ignorer cet email.')
             ->salutation("Cordialement,\n" . config('app.name'));
     }
@@ Line 102 @@
     {
         return $this->otp->channels !== null
             && is_array($this->otp->channels)
-            && !empty($this->otp->channels);
+            && $this->otp->channels !== [];
     }

     /**
@@ Line 114 @@
     {
         $name = $this->extractNotifiableName($notifiable);

-        return "Bonjour {$name} !";
+        return sprintf('Bonjour %s !', $name);
     }

     /**
    ----------- end diff -----------

Applied rules:
 * EncapsedStringsToSprintfRector
 * DisallowedEmptyRuleFixerRector


4) /home/andy-kani/pro/sites/packages/laravel-otp/src/Services/OtpInstallerService.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Otp\Services;

+use Carbon\Carbon;
+use Exception;
 use Illuminate\Console\Command;
 use Illuminate\Support\Facades\Artisan;
 use Illuminate\Support\Facades\File;
@@ Line 107 @@
      */
     private function isAlreadyInstalled(): bool
     {
-        return File::exists(config_path('otp.php')) || $this->hasCoreTables();
+        if (File::exists(config_path('otp.php'))) {
+            return true;
+        }
+        return $this->hasCoreTables();
     }

     /**
@@ Line 183 @@
      */
     private function generateMigrationFileName(): string
     {
-        $timestamp = date('Y_m_d_His');
+        $timestamp = Carbon::now()
+            ->format('Y_m_d_His');

-        return "{$timestamp}_create_one_time_passwords_table.php";
+        return $timestamp . '_create_one_time_passwords_table.php';
     }

     /**
@@ Line 223 @@
             Artisan::call('migrate', ['--force' => true]);
             $command->info(Artisan::output());
             $command->info('   ✅ Migrations completed successfully.');
-        } catch (\Exception $exception) {
+        } catch (Exception $exception) {
             $command->error('   ❌ Migration failed: ' . $exception->getMessage());
         }
     }
@@ Line 239 @@
                     return true;
                 }
             }
-        } catch (\Exception $exception) {
+        } catch (Exception $exception) {
             return false;
         }
    ----------- end diff -----------

Applied rules:
 * DateFuncCallToCarbonRector
 * EncapsedStringsToSprintfRector
 * ReturnBinaryOrToEarlyReturnRector


5) /home/andy-kani/pro/sites/packages/laravel-otp/src/Services/OtpService.php:4

    ---------- begin diff ----------
@@ Line 4 @@

 namespace Kani\Otp\Services;

+use Exception;
 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Support\Facades\Hash;
 use Illuminate\Support\Facades\Log;
@@ Line 175 @@
     ): OtpResponseData {
         $pendingOtp = $this->findPendingOtp($otpable, $type, $destination);

-        if (!$pendingOtp) {
+        if (!$pendingOtp instanceof OneTimePassword) {
             return $this->send($otpable, $type, $destination, $channels, $metadata, $expiresInMinutes, $maxAttempts);
         }

@@ Line 242 @@
             $waitSeconds = $this->getAvailableInSeconds($rateLimitKey);

             return OtpResponseData::rateLimited(
-                sprintf("Too many verification attempts. Please try again in {$waitSeconds} seconds.")
+                sprintf('Too many verification attempts. Please try again in %d seconds.', $waitSeconds)
             );
         }

         $otpRecord = $this->findValidOtp($otpable, $type, $destination);

-        if (!$otpRecord) {
+        if (!$otpRecord instanceof OneTimePassword) {
             RateLimiter::hit($rateLimitKey, 300);

             return OtpResponseData::notFound('Invalid or expired OTP code.');
@@ Line 276 @@
             }

             $message = $remainingAttempts > 1
-                ? "Invalid OTP code. You have {$remainingAttempts} attempts remaining."
+                ? sprintf('Invalid OTP code. You have %s attempts remaining.', $remainingAttempts)
                 : 'Invalid OTP code. You have 1 attempt remaining.';

             return OtpResponseData::invalidCode($message);
@@ Line 309 @@
         $cancelledCount = $otpable->cancelOtps($type, $destination);

         $message = $cancelledCount > 0
-            ? "{$cancelledCount} OTP(s) cancelled successfully."
+            ? $cancelledCount . ' OTP(s) cancelled successfully.'
             : 'No pending OTPs found to cancel.';

         return OtpResponseData::success(
@@ Line 327 @@
             $otpable->notify(new OtpNotification($otpRecord));

             return true;
-        } catch (\Exception $exception) {
+        } catch (Exception $exception) {
             Log::error('Failed to send OTP notification', [
                 'otpable_type' => $otpable->getMorphClass(),
                 'otpable_id' => $otpable->getKey(),
    ----------- end diff -----------

Applied rules:
 * UnwrapSprintfOneArgumentRector
 * FlipTypeControlToUseExclusiveTypeRector
 * EncapsedStringsToSprintfRector
 * NullableCompareToNullRector


6) /home/andy-kani/pro/sites/packages/laravel-otp/src/Traits/HasOneTimePasswords.php:4

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


 [OK] 6 files would have been changed (dry-run) by Rector                                                               

