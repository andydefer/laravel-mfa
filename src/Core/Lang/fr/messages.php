<?php

declare(strict_types=1);

return [
    // Notification email messages
    'subject' => 'Votre code de vérification - :app_name',
    'greeting' => 'Bonjour %s !',
    'intro' => 'Veuillez utiliser le code de vérification ci-dessous :',
    'expires_in' => 'Ce code expirera dans :minutes minute(s).',
    'ignore_request' => "Si vous n'avez pas demandé cette vérification, veuillez ignorer cet email.",
    'salutation' => "Cordialement,\n:app_name",
    'default_user_name' => 'Utilisateur',

    // Success messages
    'send_success' => 'Code de vérification envoyé avec succès.',
    'resend_success' => 'Code de vérification renvoyé avec succès.',
    'verify_success' => 'OTP vérifié avec succès.',
    'cancel_success' => ':count OTP(s) annulé(s) avec succès.',
    'no_pending_to_cancel' => 'Aucun OTP en attente à annuler.',

    // Error messages
    'send_failed' => 'Impossible d\'envoyer l\'OTP. Veuillez réessayer.',
    'resend_failed' => 'Impossible de renvoyer l\'OTP. Veuillez réessayer.',
    'otp_not_found' => 'Code OTP invalide ou expiré.',
    'expired_code' => 'Le code OTP a expiré. Veuillez en demander un nouveau.',
    'max_attempts_exceeded' => 'Nombre maximum de tentatives dépassé. Veuillez demander un nouvel OTP.',
    'invalid_code_attempts_remaining' => 'Code OTP invalide. Il vous reste :attempts tentative(s).',
    'invalid_code_one_attempt_remaining' => 'Code OTP invalide. Il vous reste 1 tentative.',
    'rate_limited' => 'Veuillez patienter :seconds secondes avant de demander un nouvel OTP.',
];
