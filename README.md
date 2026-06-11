# Laravel MFA (Multi-Factor Authentication)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andydefer/laravel-mfa.svg)](https://packagist.org/packages/andydefer/laravel-mfa)
[![PHP Version Require](https://img.shields.io/packagist/php-v/andydefer/laravel-mfa.svg)](https://packagist.org/packages/andydefer/laravel-mfa)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/andydefer/laravel-mfa.svg)](https://packagist.org/packages/andydefer/laravel-mfa)
[![Total Downloads](https://img.shields.io/packagist/dt/andydefer/laravel-mfa.svg)](https://packagist.org/packages/andydefer/laravel-mfa)

Un package complet de Multi-Factor Authentication pour Laravel 12, supportant OTP (email/SMS) et TOTP (Google Authenticator).

## ✨ Fonctionnalités

- **🔑 OTP (One-Time Password)** - Envoi et vérification de codes à usage unique par email ou SMS
- **🔐 TOTP (Time-based One-Time Password)** - Authentification à deux facteurs compatible Google Authenticator
- **📱 QR Codes** - Génération automatique de QR codes pour l'application Google Authenticator
- **🔁 Codes de récupération** - Génération de codes de secours pour récupérer l'accès
- **⏱️ Rate Limiting** - Protection contre les attaques par force brute
- **🌍 Multilingue** - Support français et anglais inclus (extensible)
- **🧹 Auto-cleanup** - Nettoyage automatique des OTPs expirés et anciennes configurations 2FA
- **🔄 Polymorphique** - Supporte n'importe quel modèle Eloquent (User, Admin, etc.)

## 📋 Prérequis

- PHP 8.1 ou supérieur
- Laravel 12.x
- Composer

## 🚀 Installation

### 1. Installer via Composer

```bash
composer require andydefer/laravel-mfa
```

### 2. Publier les fichiers de configuration et migrations

```bash
php artisan mfa:install
```

Cette commande va :
- Publier le fichier de configuration `config/mfa.php`
- Publier les migrations OTP et TOTP
- Exécuter les migrations

**Options disponibles :**

```bash
# Forcer l'installation sans confirmation
php artisan mfa:install --force

# Installer sans exécuter les migrations
php artisan mfa:install --no-migrate

# Installer uniquement l'OTP (sans le 2FA/TOTP)
php artisan mfa:install --without-totp

# Installer uniquement le TOTP/2FA (sans l'OTP)
php artisan mfa:install --without-otp
```

### 3. Migrer la base de données (si non fait automatiquement)

```bash
php artisan migrate
```

## ⚙️ Configuration

### Variables d'environnement

Créez/modifiez les variables suivantes dans votre fichier `.env` :

```env
# OTP Settings
MFA_OTP_DEFAULT_EXPIRY_MINUTES=10
MFA_OTP_DEFAULT_MAX_ATTEMPTS=3
MFA_OTP_LOCALE=en
MFA_OTP_RATE_LIMIT_REQUESTS=3
MFA_OTP_RATE_LIMIT_VERIFICATIONS=5

# TOTP Settings
MFA_TOTP_PERIOD=30
MFA_TOTP_DIGITS=6
MFA_TOTP_ALGORITHM=sha1
MFA_TOTP_WINDOW=1
MFA_TOTP_ISSUER=MyApplication

# Recovery Codes
MFA_RECOVERY_CODES_COUNT=8
MFA_RECOVERY_CODE_LENGTH=10
MFA_RECOVERY_CODE_HASH_ALGO=sha256

# Cleanup
MFA_CLEANUP_AUTO_CLEANUP=true
MFA_CLEANUP_RETENTION_DAYS=30
```

### Fichier de configuration

Après installation, vous pouvez personnaliser `config/mfa.php` :

```php
return [
    'otp' => [
        'default_expiry_minutes' => env('MFA_OTP_DEFAULT_EXPIRY_MINUTES', 10),
        'default_max_attempts' => env('MFA_OTP_DEFAULT_MAX_ATTEMPTS', 3),
        'localization' => [
            'locale' => env('MFA_OTP_LOCALE', 'en'),
            'supported_locales' => ['fr', 'en'],
            'fallback_locale' => env('MFA_OTP_FALLBACK_LOCALE', 'en'),
        ],
        // ... autres configurations
    ],
    'totp' => [
        'period' => env('MFA_TOTP_PERIOD', 30),
        'digits' => env('MFA_TOTP_DIGITS', 6),
        'algorithm' => env('MFA_TOTP_ALGORITHM', 'sha1'),
        'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),
        'window' => env('MFA_TOTP_WINDOW', 1),
    ],
    // ...
];
```

## 🔧 Utilisation

### 1. Préparer votre modèle User

Ajoutez les traits à votre modèle `User` :

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use AndyDefer\Mfa\Otp\Traits\HasOneTimePasswords;
use AndyDefer\Mfa\Totp\Traits\HasTwoFactorAuthentication;

class User extends Authenticatable
{
    use HasOneTimePasswords;
    use HasTwoFactorAuthentication;
    
    // Vos autres configurations...
}
```

### 2. Interface pour les canaux de livraison (optionnel)

Si vous souhaitez personnaliser les canaux de livraison OTP (email, SMS, WhatsApp) :

```php
<?php

namespace App\Models;

use AndyDefer\Mfa\Otp\Contracts\MustOtpChannels;

class User extends Authenticatable implements MustOtpChannels
{
    use HasOneTimePasswords;
    use HasTwoFactorAuthentication;
    
    /**
     * Définit les canaux de livraison OTP pour cet utilisateur.
     *
     * @return array<int, string>
     */
    public function getOtpChannels(): array
    {
        return ['mail', 'sms']; // Email et SMS
    }
}
```

## 📱 OTP (One-Time Password)

### Envoyer un OTP

```php
// Envoi simple
$response = $user->sendOtp(
    type: 'email_verification',
    destination: 'user@example.com'
);

if ($response->isSuccess()) {
    // OTP envoyé avec succès
    $expiresAt = $response->data['expires_at'];
    $expiresInMinutes = $response->data['expires_in_minutes'];
}

// Envoi avec options personnalisées
$response = $user->sendOtp(
    type: 'login',
    destination: '+33612345678',
    channels: ['sms'],                    // Canal de livraison
    meta: ['ip' => request()->ip()],      // Métadonnées
    expiresInMinutes: 5,                  // Expire dans 5 minutes
    maxAttempts: 2                        // Maximum 2 tentatives
);
```

### Renvoyer un OTP

```php
$response = $user->resendOtp(
    type: 'email_verification',
    destination: 'user@example.com'
);

if ($response->isSuccess()) {
    // Nouvel OTP envoyé, l'ancien a été annulé
}
```

### Vérifier un OTP

```php
$response = $user->verifyOtp(
    code: $request->code,
    type: 'email_verification',
    destination: $request->email,
    consume: true  // Marquer comme utilisé après vérification
);

if ($response->isSuccess()) {
    // OTP valide ✅
} else {
    // OTP invalide
    switch ($response->status->value) {
        case 'invalid_code':
            // Code incorrect
            break;
        case 'expired_code':
            // Code expiré
            break;
        case 'max_attempts_exceeded':
            // Trop de tentatives
            break;
        case 'rate_limited':
            // Trop de demandes, attendez
            break;
    }
}
```

### Annuler des OTPs en attente

```php
$response = $user->cancelOtps('email_verification', 'user@example.com');
// Retourne le nombre d'OTPs annulés
```

### Vérifier l'existence d'un OTP valide

```php
if ($user->hasValidOtp('email_verification', 'user@example.com')) {
    // Un OTP valide existe
}

$pendingOtp = $user->getPendingOtp('email_verification', 'user@example.com');
```

## 🔐 TOTP / 2FA (Google Authenticator)

### Initialiser la configuration 2FA

```php
// Obtenir ou créer un secret TOTP
$secret = $user->getTwoFactorSecret();

// Générer l'URI pour le QR code
$qrCodeUri = $user->getTwoFactorQrCodeUri();

// Générer un QR code (exemple avec Simple QrCode)
use SimpleSoftwareIO\QrCode\Facades\QrCode;

$qrCode = QrCode::size(200)->generate($qrCodeUri);
```

### Activer la 2FA

```php
// L'utilisateur scanne le QR code et fournit le code à 6 chiffres
$code = $request->input('code'); // Code de l'application Authenticator

$enabled = $user->enableTwoFactor($code);

if ($enabled) {
    // 2FA activée avec succès ! ✅
    
    // Générer les codes de récupération à montrer à l'utilisateur
    $recoveryCodes = $user->generateRecoveryCodes();
    
    // Affichez ces codes à l'utilisateur (une seule fois !)
    foreach ($recoveryCodes as $code) {
        echo $code . PHP_EOL;
    }
}
```

### Désactiver la 2FA

```php
$disabled = $user->disableTwoFactor();

if ($disabled) {
    // 2FA désactivée
}
```

### Vérifier le code 2FA lors de la connexion

```php
// Lors de la connexion, après vérification du mot de passe
$code = $request->input('2fa_code'); // Code à 6 chiffres

if ($user->verifyTwoFactorCode($code)) {
    // Code valide - Connexion autorisée ✅
    Auth::login($user);
} else {
    // Code invalide
    return back()->withErrors(['2fa_code' => 'Code invalide']);
}
```

### Codes de récupération

```php
// Générer de nouveaux codes de récupération
$recoveryCodes = $user->generateRecoveryCodes();

// Vérifier un code de récupération (pendant la connexion)
if ($user->verifyTwoFactorCode($recoveryCodeFromUser)) {
    // Code de récupération valide - Connexion autorisée
    // Le code est automatiquement consommé (ne peut plus être réutilisé)
}

// Obtenir les codes de récupération stockés (hachés)
$storedCodes = $user->getRecoveryCodes();
```

## 🗄️ Nettoyage automatique

### Commande manuelle

```bash
# Nettoyer tous les OTPs expirés et vieilles configurations 2FA
php artisan mfa:cleanup

# Forcer sans confirmation
php artisan mfa:cleanup --force

# Simuler (dry run) - voir ce qui serait supprimé
php artisan mfa:cleanup --dry-run

# Garder les OTPs expirés, ne nettoyer que les utilisés/vérifiés
php artisan mfa:cleanup --keep-expired

# Nettoyer uniquement les OTPs
php artisan mfa:cleanup --otp-only

# Nettoyer uniquement les configurations 2FA
php artisan mfa:cleanup --totp-only

# Filtrer par type d'OTP
php artisan mfa:cleanup --type=email_verification

# Jours de rétention personnalisés
php artisan mfa:cleanup --days=15
```

### Configuration automatique

Dans `config/mfa.php` :

```php
'cleanup' => [
    'auto_cleanup' => env('MFA_CLEANUP_AUTO_CLEANUP', true),  // Nettoyage auto
    'frequency' => env('MFA_CLEANUP_FREQUENCY', 60),          // Toutes les 60 minutes
    'retention_days' => env('MFA_CLEANUP_RETENTION_DAYS', 30), // Rétention 30 jours
],
```

## 🌍 Traductions

### Configurer la langue

Dans `.env` :

```env
MFA_OTP_LOCALE=fr    # Français
# ou
MFA_OTP_LOCALE=en    # Anglais
```

### Utiliser le helper de traduction

```php
use AndyDefer\Mfa\Core\Helpers\TranslationHelper;

// Traduire un message
$message = TranslationHelper::trans('messages.send_success');
// Retourne: "Verification code sent successfully." ou "Code de vérification envoyé avec succès."

// Avec placeholders
$message = TranslationHelper::trans('messages.subject', ['app_name' => 'MyApp']);
// Retourne: "Your verification code - MyApp"
```

### Publier les fichiers de langue pour personnalisation

```bash
php artisan vendor:publish --tag=mfa-translations
```

Les fichiers seront copiés dans `resources/lang/vendor/mfa/`.

## 🎯 Exemples complets

### Exemple 1 : Vérification d'email

```php
// UserController.php
public function sendVerification(Request $request)
{
    $user = auth()->user();
    
    $response = $user->sendOtp(
        type: 'email_verification',
        destination: $user->email
    );
    
    if ($response->isSuccess()) {
        return response()->json([
            'message' => 'Code de vérification envoyé',
            'expires_in' => $response->data['expires_in_minutes']
        ]);
    }
    
    return response()->json(['error' => $response->message], 429);
}

public function verifyEmail(Request $request)
{
    $user = auth()->user();
    
    $response = $user->verifyOtp(
        code: $request->code,
        type: 'email_verification',
        destination: $user->email
    );
    
    if ($response->isSuccess()) {
        $user->email_verified_at = now();
        $user->save();
        
        return response()->json(['message' => 'Email vérifié avec succès']);
    }
    
    return response()->json(['error' => $response->message], 422);
}
```

### Exemple 2 : Authentification à deux facteurs complète

```php
// TwoFactorController.php
class TwoFactorController extends Controller
{
    public function showSetup()
    {
        $user = auth()->user();
        
        // Obtenir ou créer le secret
        $secret = $user->getTwoFactorSecret();
        
        // Générer l'URI du QR code
        $qrCodeUri = $user->getTwoFactorQrCodeUri();
        
        // Générer un QR code (utilisez votre bibliothèque préférée)
        $qrCode = QrCode::size(200)->generate($qrCodeUri);
        
        return view('2fa.setup', compact('qrCode', 'secret'));
    }
    
    public function enable(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'code' => 'required|string|size:6'
        ]);
        
        if ($user->enableTwoFactor($request->code)) {
            // Générer et montrer les codes de récupération
            $recoveryCodes = $user->generateRecoveryCodes();
            
            return view('2fa.recovery-codes', compact('recoveryCodes'));
        }
        
        return back()->withErrors(['code' => 'Code invalide']);
    }
    
    public function disable()
    {
        $user = auth()->user();
        $user->disableTwoFactor();
        
        return redirect()->route('profile')->with('success', '2FA désactivée');
    }
    
    public function verify(Request $request)
    {
        $user = auth()->user();
        
        if ($user->verifyTwoFactorCode($request->code)) {
            // Stocker en session que le 2FA est vérifié
            session(['2fa_verified' => true]);
            
            return redirect()->intended('/dashboard');
        }
        
        return back()->withErrors(['code' => 'Code invalide']);
    }
    
    public function showRecovery()
    {
        $user = auth()->user();
        
        if (!$user->isTwoFactorEnabled()) {
            return redirect()->route('login');
        }
        
        return view('2fa.recovery');
    }
    
    public function verifyRecovery(Request $request)
    {
        $user = auth()->user();
        
        if ($user->verifyTwoFactorCode($request->code)) {
            session(['2fa_verified' => true]);
            
            // Optionnel : régénérer de nouveaux codes
            $newCodes = $user->generateRecoveryCodes();
            
            return redirect()->intended('/dashboard');
        }
        
        return back()->withErrors(['code' => 'Code de récupération invalide']);
    }
}
```

### Exemple 3 : Middleware pour protection 2FA

```php
// app/Http/Middleware/RequireTwoFactor.php
namespace App\Http\Middleware;

use Closure;

class RequireTwoFactor
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        
        // Si 2FA est activée et non vérifiée dans cette session
        if ($user && $user->isTwoFactorEnabled() && !session('2fa_verified')) {
            return redirect()->route('2fa.verify');
        }
        
        return $next($request);
    }
}

// Dans Kernel.php
protected $routeMiddleware = [
    // ...
    '2fa' => \App\Http\Middleware\RequireTwoFactor::class,
];

// Dans web.php
Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    // Routes protégées par 2FA...
});
```

## 🧪 Test du package

Le package inclut une suite complète de tests. Pour les exécuter :

```bash
composer test
```

Ou avec PHPUnit directement :

```bash
./vendor/bin/phpunit
```

## 📊 API Reference

### Trait `HasOneTimePasswords`

| Méthode | Paramètres | Retour | Description |
|---------|------------|--------|-------------|
| `sendOtp()` | `string $type, string $destination, ?array $channels, ?array $meta, ?int $expiresInMinutes, ?int $maxAttempts` | `OtpResponseData` | Envoie un nouvel OTP |
| `resendOtp()` | `string $type, string $destination, ?array $channels, ?array $meta, ?int $expiresInMinutes, ?int $maxAttempts` | `OtpResponseData` | Renvoie un OTP |
| `verifyOtp()` | `string $code, string $type, string $destination, bool $consume = true` | `OtpResponseData` | Vérifie un OTP |
| `cancelOtps()` | `string $type, string $destination` | `int` | Annule les OTPs en attente |
| `hasValidOtp()` | `string $type, string $destination` | `bool` | Vérifie si un OTP valide existe |
| `getPendingOtp()` | `string $type, string $destination` | `?OneTimePassword` | Récupère l'OTP en attente |
| `cleanupExpiredOtps()` | - | `int` | Nettoie les OTPs expirés |

### Trait `HasTwoFactorAuthentication`

| Méthode | Paramètres | Retour | Description |
|---------|------------|--------|-------------|
| `getTwoFactorSecret()` | - | `TwoFactorSecret` | Obtient ou crée un secret TOTP |
| `isTwoFactorEnabled()` | - | `bool` | Vérifie si la 2FA est activée |
| `enableTwoFactor()` | `string $code` | `bool` | Active la 2FA avec vérification du code |
| `disableTwoFactor()` | - | `bool` | Désactive la 2FA |
| `verifyTwoFactorCode()` | `string $code` | `bool` | Vérifie un code TOTP ou de récupération |
| `getTwoFactorQrCodeUri()` | - | `string` | URI du QR code pour Google Authenticator |
| `generateRecoveryCodes()` | - | `array` | Génère de nouveaux codes de récupération |
| `getRecoveryCodes()` | - | `array` | Récupère les codes de récupération stockés |

### Types de réponse `OtpResponseData`

```php
$response = $user->sendOtp(...);

// Méthodes
$response->isSuccess();  // bool
$response->isFailed();   // bool
$response->toArray();    // array

// Propriétés
$response->status;       // OtpStatus enum
$response->errorCode;    // ErrorCode|null
$response->message;      // string|null
$response->data;         // array|null
```

### Enums

**OtpStatus**: `SUCCESS`, `FAILED`, `RATE_LIMITED`, `INVALID_CODE`, `EXPIRED_CODE`, `MAX_ATTEMPTS_EXCEEDED`, `NOT_FOUND`, `SEND_FAILED`, `RESEND_FAILED`

**ErrorCode**: `RATE_LIMIT_EXCEEDED`, `OTP_NOT_FOUND`, `INVALID_OTP`, `MAX_ATTEMPTS_EXCEEDED`, `OTP_SEND_FAILED`, `OTP_RESEND_FAILED`, `OTP_EXPIRED`

## 🔒 Sécurité

- Les codes OTP sont hachés avec `Hash::make()` avant stockage
- Les codes de récupération sont hachés avec SHA-256 (ou configurable)
- Rate limiting intégré pour prévenir les attaques par force brute
- Fenêtre de validation TOTP configurable pour tolérer le décalage horaire
- Utilisation de `hash_equals()` pour les comparaisons résistantes aux timing attacks
- Génération cryptographique sécurisée avec `random_int()`

## 🐛 Dépannage

### Problème : Les emails OTP ne sont pas envoyés

**Solution** : Vérifiez la configuration mail de Laravel et que votre modèle implémente `Notifiable`.

### Problème : QR code non scannable

**Solution** : Assurez-vous que la configuration `issuer` dans `mfa.totp.issuer` ne contient pas d'espaces ou caractères spéciaux.

### Problème : Codes 2FA invalides

**Solution** : Augmentez la fenêtre de validation `window` dans la configuration TOTP (ex: `window=2` pour tolérer ±60 secondes).

### Problème : Translations non chargées

**Solution** : Publiez et personnalisez les fichiers de langue :
```bash
php artisan vendor:publish --tag=mfa-translations --force
```

## 🤝 Contribution

Les contributions sont les bienvenues ! Veuillez :

1. Fork le projet
2. Créer une branche (`git checkout -b feature/amazing-feature`)
3. Commiter vos changements (`git commit -m 'feat: add amazing feature'`)
4. Pusher (`git push origin feature/amazing-feature`)
5. Ouvrir une Pull Request

## 📄 License

MIT License - voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Crédits

- [OTPHP](https://github.com/Spomky-Labs/otphp) - Bibliothèque TOTP
- [BaconQrCode](https://github.com/Bacon/BaconQrCode) - Générateur de QR codes
- [ParagonIE Constant Time Encoding](https://github.com/paragonie/constant_time_encoding) - Encodage Base32 sécurisé

## 📞 Support

- Documentation : [https://github.com/andydefer/laravel-mfa](https://github.com/andydefer/laravel-mfa)
- Issues : [https://github.com/andydefer/laravel-mfa/issues](https://github.com/andydefer/laravel-mfa/issues)

---

**Fait avec ❤️ pour la communauté Laravel**
