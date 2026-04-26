<?php

declare(strict_types=1);

return [
    // Notification email messages
    'subject' => 'Your verification code - :app_name',
    'greeting' => 'Hello %s!',
    'intro' => 'Please use the verification code below:',
    'expires_in' => 'This code will expire in :minutes minute(s).',
    'ignore_request' => 'If you did not request this verification, please ignore this email.',
    'salutation' => "Sincerely,\n:app_name",
    'default_user_name' => 'User',

    // Success messages
    'send_success' => 'Verification code sent successfully.',
    'resend_success' => 'Verification code resent successfully.',
    'verify_success' => 'OTP verified successfully.',
    'cancel_success' => ':count OTP(s) cancelled successfully.',
    'no_pending_to_cancel' => 'No pending OTPs found to cancel.',

    // Error messages
    'send_failed' => 'Unable to send OTP. Please try again.',
    'resend_failed' => 'Unable to resend OTP. Please try again.',
    'otp_not_found' => 'Invalid or expired OTP code.',
    'expired_code' => 'OTP code has expired. Please request a new one.',
    'max_attempts_exceeded' => 'Maximum verification attempts exceeded. Please request a new OTP.',
    'invalid_code_attempts_remaining' => 'Invalid OTP code. You have :attempts attempts remaining.',
    'invalid_code_one_attempt_remaining' => 'Invalid OTP code. You have 1 attempt remaining.',
    'rate_limited' => 'Please wait :seconds seconds before requesting another OTP.',
];
