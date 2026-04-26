# Laravel OTP — Gestion de mots de passe à usage unique pour Laravel

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-11.x-12.x-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Tests](https://img.shields.io/badge/tests-100%2B%20passing-brightgreen)

**Laravel OTP** est un package complet pour la gestion de **mots de passe à usage unique** (One-Time Passwords). Il permet d'envoyer, vérifier, renvoyer et annuler des codes OTP avec une sécurité renforcée.

---

## 📦 Installation

### Installation via Composer

```bash
composer require andydefer/laravel-otp
```

### Installation automatique (recommandée)

```bash
php artisan otp:install
```

Cette commande :
- Publie le fichier de configuration `config/otp.php`
- Publie les migrations
- Exécute les migrations

### Installation manuelle

```bash
# Publier la configuration
php artisan vendor:publish --tag=otp-config

# Publier les migrations
php artisan vendor:publish --tag=otp-migrations

# Publier les traductions (optionnel)
php artisan vendor:publish --tag=otp-translations

# Exécuter les migrations
php artisan migrate
```

### Vérifier l'installation

```bash
php artisan migrate:status
# Vous devez voir "one_time_passwords" dans la liste

php artisan list | grep otp
# Vous devez voir les commandes otp:install et otp:cleanup
```

---

## 🚀 Démarrage rapide

### 1. Ajouter le trait à votre modèle

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Kani\Mfa\Traits\HasOneTimePasswords;

class User extends Authenticatable
{
    use HasOneTimePasswords;
}
```

### 2. Envoyer un code OTP

```php
$user = User::find(1);

$response = $user->sendOtp(
    type: 'email_verification',
    destination: $user->email,
    channels: ['email']
);

if ($response->isSuccess()) {
    // Un code à 6 chiffres a été envoyé
    $expiresAt = $response->data['expires_at'];
}
```

### 3. Vérifier le code

```php
$response = $user->verifyOtp(
    code: $request->code,
    type: 'email_verification',
    destination: $user->email,
    consume: true
);

if ($response->isSuccess()) {
    $user->email_verified_at = now();
    $user->save();
}
```

### 4. Renvoyer un code (si l'utilisateur ne l'a pas reçu)

```php
$response = $user->resendOtp(
    type: 'email_verification',
    destination: $user->email
);

if ($response->isSuccess()) {
    // Un nouveau code a été envoyé
    // L'ancien code est automatiquement annulé
}
```

---

## 📖 Concepts fondamentaux

### Qu'est-ce qu'un OTP ?

Un OTP (One-Time Password) est un code à usage unique, généralement à 6 chiffres, qui expire après un court délai (10 minutes par défaut). Il est envoyé par email ou SMS pour vérifier l'identité d'un utilisateur.

### Le polymorphisme : un seul système pour tous vos modèles

Le package utilise le polymorphisme Laravel. Cela signifie qu'**un seul système fonctionne avec tous vos modèles**.

| Sans polymorphisme | Avec polymorphisme |
|-------------------|-------------------|
| Table `user_otps` | Table unique `one_time_passwords` |
| Table `doctor_otps` | Colonne `otpable_type` = modèle concerné |
| Table `admin_otps` | Colonne `otpable_id` = ID du modèle |

**Exemple** :

```php
// Le même code fonctionne pour User
$user->sendOtp('login', $user->email);

// Et pour Doctor
$doctor->sendOtp('login', $doctor->email);

// Et pour Admin
$admin->sendOtp('login', $admin->email);
```

### Les trois entités principales

| Entité | Rôle | Méthodes principales |
|--------|------|---------------------|
| **OneTimePassword** (modèle) | Stocke les OTPs en base | `isExpired()`, `verifyCode()`, `markAsUsed()` |
| **OtpService** (service) | Contient la logique métier | `send()`, `verify()`, `resend()`, `cancel()` |
| **HasOneTimePasswords** (trait) | Interface pour les modèles | `sendOtp()`, `verifyOtp()`, `resendOtp()` |

### Le cycle de vie d'un OTP

```
1. sendOtp()
   ├── Vérifie le rate limiting
   ├── Supprime les anciens OTPs en attente
   ├── Génère un code (ex: 123456)
   ├── Hache le code (ex: $2y$10$...)
   ├── Stocke le hash en base
   ├── Envoie le code en clair par email/SMS
   └── Retourne la réponse

2. L'utilisateur saisit le code

3. verifyOtp()
   ├── Vérifie le rate limiting
   ├── Recherche l'OTP correspondant
   ├── Vérifie l'expiration
   ├── Vérifie le code
   ├── Incrémente les tentatives si erreur
   ├── Marque comme vérifié si succès
   ├── Marque comme utilisé si demandé
   └── Retourne la réponse

4. resendOtp()
   ├── Vérifie le rate limiting
   ├── Annule l'ancien OTP (s'il existe)
   ├── Génère un nouveau code
   ├── Stocke le nouveau hash
   ├── Envoie le nouveau code
   └── Retourne la réponse
```

---

## 🛠️ Installation détaillée

### Structure des fichiers publiés

```
config/
└── otp.php                     # Configuration du package

database/migrations/
└── 2026_01_15_000001_create_one_time_passwords_table.php

resources/lang/vendor/otp/
├── en/
│   └── messages.php           # Traductions anglaises
└── fr/
    └── messages.php           # Traductions françaises
```

### Configuration (`config/otp.php`)

```php
<?php

return [
    // Paramètres par défaut des OTPs
    'default_expiry_minutes' => env('OTP_DEFAULT_EXPIRY_MINUTES', 10),
    'default_max_attempts' => env('OTP_DEFAULT_MAX_ATTEMPTS', 3),

    // Paramètres de nettoyage automatique
    'cleanup' => [
        'auto_cleanup' => env('OTP_AUTO_CLEANUP', true),
        'frequency' => env('OTP_CLEANUP_FREQUENCY', 60),
        'retention_days' => env('OTP_RETENTION_DAYS', 30),
    ],

    // Paramètres de sécurité (rate limiting)
    'security' => [
        'rate_limit_requests' => env('OTP_RATE_LIMIT_REQUESTS', 3),
        'rate_limit_verifications' => env('OTP_RATE_LIMIT_VERIFICATIONS', 5),
        'rate_limit_decay_minutes' => env('OTP_RATE_LIMIT_DECAY_MINUTES', 60),
        'failed_verification_decay_seconds' => env('OTP_FAILED_VERIFICATION_DECAY_SECONDS', 300),
        'rate_limit_hit_decay_seconds' => env('OTP_RATE_LIMIT_HIT_DECAY_SECONDS', 60),
    ],

    // Paramètres de localisation (multi-langues)
    'localization' => [
        'locale' => env('OTP_LOCALE', 'fr'),
        'supported_locales' => ['fr', 'en'],
        'fallback_locale' => env('OTP_FALLBACK_LOCALE', 'en'),
    ],
];
```

### Variables d'environnement

```env
# .env
OTP_DEFAULT_EXPIRY_MINUTES=10
OTP_DEFAULT_MAX_ATTEMPTS=3
OTP_AUTO_CLEANUP=true
OTP_RATE_LIMIT_REQUESTS=3
OTP_RATE_LIMIT_VERIFICATIONS=5
OTP_LOCALE=fr
OTP_FALLBACK_LOCALE=en
```

---

## 📊 Structure de la base de données

### Table `one_time_passwords`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint | Clé primaire |
| `otpable_type` | string | Type du modèle (polymorphisme) |
| `otpable_id` | bigint | ID du modèle |
| `token_hash` | string(64) | Hash du code (bcrypt) |
| `type` | string(50) | Type d'OTP (email_verification, login, 2fa, etc.) |
| `destination` | string(255) | Destination (email, téléphone) |
| `channels` | json | Canaux de livraison (['email'], ['sms'], etc.) |
| `meta` | json | Métadonnées (IP, user_agent, etc.) |
| `attempts` | integer | Tentatives de vérification |
| `max_attempts` | integer | Maximum de tentatives autorisées |
| `expires_at` | timestamp | Date d'expiration |
| `verified_at` | timestamp | Date de vérification (nullable) |
| `used_at` | timestamp | Date d'utilisation (nullable) |
| `cancelled_at` | timestamp | Date d'annulation (nullable) |
| `created_at` | timestamp | Date de création |
| `updated_at` | timestamp | Date de mise à jour |

---

## 🎯 Utilisation complète du trait `HasOneTimePasswords`

### `sendOtp()` - Envoyer un OTP

```php
public function sendOtp(
    string $type,
    string $destination,
    ?array $channels = null,
    ?array $meta = null,
    ?int $expiresInMinutes = null,
    ?int $maxAttempts = null
): OtpResponseData
```

**Paramètres** :

| Paramètre | Type | Requis | Défaut | Description |
|-----------|------|--------|--------|-------------|
| `type` | string | Oui | - | Type d'OTP (email_verification, login, 2fa, etc.) |
| `destination` | string | Oui | - | Adresse email ou numéro de téléphone |
| `channels` | array|null | Non | `['mail']` | Canaux de livraison |
| `meta` | array|null | Non | `null` | Métadonnées (IP, user_agent, etc.) |
| `expiresInMinutes` | int|null | Non | `config('otp.default_expiry_minutes')` | Durée de validité en minutes |
| `maxAttempts` | int|null | Non | `config('otp.default_max_attempts')` | Nombre maximum de tentatives |

**Exemples** :

```php
// Exemple basique
$response = $user->sendOtp('email_verification', $user->email);

// Exemple avec canaux personnalisés
$response = $user->sendOtp(
    type: '2fa',
    destination: $user->email,
    channels: ['email', 'sms']
);

// Exemple avec métadonnées
$response = $user->sendOtp(
    type: 'delete_account',
    destination: $user->email,
    meta: [
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent()
    ]
);

// Exemple avec paramètres personnalisés
$response = $user->sendOtp(
    type: 'payment_confirmation',
    destination: $user->email,
    expiresInMinutes: 5,
    maxAttempts: 2
);
```

### `verifyOtp()` - Vérifier un OTP

```php
public function verifyOtp(
    string $code,
    string $type,
    string $destination,
    bool $consume = true
): OtpResponseData
```

**Paramètres** :

| Paramètre | Type | Requis | Défaut | Description |
|-----------|------|--------|--------|-------------|
| `code` | string | Oui | - | Code saisi par l'utilisateur |
| `type` | string | Oui | - | Type d'OTP (identique à sendOtp) |
| `destination` | string | Oui | - | Destination (identique à sendOtp) |
| `consume` | bool | Non | `true` | Marquer comme utilisé après vérification |

**Exemples** :

```php
// Vérification simple
$response = $user->verifyOtp('123456', 'login', $user->email);

// Vérification sans consommation (pour 2FA)
$response = $user->verifyOtp($code, '2fa', $user->email, consume: false);

if ($response->isSuccess()) {
    // Code valide, mais non consommé
    session(['2fa_passed' => true]);
}

// Vérification avec consommation explicite
$response = $user->verifyOtp($code, 'payment', $user->email, consume: true);
```

### `resendOtp()` - Renvoyer un OTP

```php
public function resendOtp(
    string $type,
    string $destination,
    ?array $channels = null,
    ?array $meta = null,
    ?int $expiresInMinutes = null,
    ?int $maxAttempts = null
): OtpResponseData
```

**Comportement** :
- Si un OTP existe en attente : il est annulé, un nouveau est créé
- Si aucun OTP n'existe : équivalent à `sendOtp()`

**Exemples** :

```php
// Renvoi simple
$response = $user->resendOtp('email_verification', $user->email);

// Renvoi avec canal différent (SMS au lieu d'email)
$response = $user->resendOtp('login', $user->phone, channels: ['sms']);

// Renvoi après expiration
if ($verifyResponse->status->value === 'expired_code') {
    $resendResponse = $user->resendOtp('login', $user->email);
}
```

### `cancelOtps()` - Annuler des OTPs

```php
public function cancelOtps(string $type, string $destination): int
```

**Retour** : Nombre d'OTPs annulés

**Exemples** :

```php
// Annuler tous les OTPs en attente pour un type et destination
$cancelled = $user->cancelOtps('email_verification', $user->email);

// Utilisation lors de la déconnexion
public function logout(Request $request)
{
    $user = auth()->user();
    
    // Annuler tous les OTPs en attente
    $user->cancelOtps('login', $user->email);
    $user->cancelOtps('2fa', $user->email);
    
    auth()->logout();
    return redirect('/');
}
```

### `getPendingOtp()` - Récupérer l'OTP en attente

```php
public function getPendingOtp(string $type, string $destination): ?OneTimePassword
```

**Exemple** :

```php
$pendingOtp = $user->getPendingOtp('email_verification', $user->email);

if ($pendingOtp) {
    $minutesLeft = now()->diffInMinutes($pendingOtp->expires_at);
    echo "Il vous reste {$minutesLeft} minutes pour utiliser votre code";
}
```

### `hasValidOtp()` - Vérifier si un OTP valide existe

```php
public function hasValidOtp(string $type, string $destination): bool
```

**Exemple** :

```php
if ($user->hasValidOtp('login', $user->email)) {
    // Afficher le formulaire de saisie
    return view('auth.verify-code');
} else {
    // Rediriger vers la demande de code
    return redirect()->route('login.request-code');
}
```

### `cleanupExpiredOtps()` - Nettoyer les OTPs expirés

```php
public function cleanupExpiredOtps(): int
```

**Exemple** :

```php
// Nettoyage manuel pour un utilisateur spécifique
$deleted = $user->cleanupExpiredOtps();
logger()->info("{$deleted} OTPs expirés supprimés pour l'utilisateur {$user->id}");
```

### `oneTimePasswords()` - Relation polymorphique

```php
public function oneTimePasswords(): MorphMany
```

**Exemple** :

```php
// Récupérer tous les OTPs d'un utilisateur
$allOtps = $user->oneTimePasswords;

// Récupérer uniquement les OTPs de connexion
$loginOtps = $user->oneTimePasswords()
    ->where('type', 'login')
    ->get();

// Compter les tentatives échouées
$failedAttempts = $user->oneTimePasswords()
    ->where('type', '2fa')
    ->sum('attempts');
```

---

## 📊 Objet `OtpResponseData`

Toutes les méthodes du package retournent un objet `OtpResponseData`.

### Structure

```php
class OtpResponseData
{
    public readonly OtpStatus $status;
    public readonly ?ErrorCode $errorCode;
    public readonly ?string $message;
    public readonly ?array $data;
    
    public function isSuccess(): bool;
    public function isFailed(): bool;
    public function toArray(): array;
}
```

### Énumération `OtpStatus`

| Valeur | Signification |
|--------|---------------|
| `SUCCESS` | Opération réussie |
| `FAILED` | Échec générique |
| `RATE_LIMITED` | Trop de tentatives, attendre |
| `INVALID_CODE` | Code OTP incorrect |
| `EXPIRED_CODE` | Code OTP expiré |
| `MAX_ATTEMPTS_EXCEEDED` | Trop de tentatives pour ce code |
| `NOT_FOUND` | Aucun OTP trouvé |
| `SEND_FAILED` | Échec de l'envoi |
| `RESEND_FAILED` | Échec du renvoi |

### Énumération `ErrorCode`

| Valeur | Code HTTP | Message |
|--------|-----------|---------|
| `RATE_LIMIT_EXCEEDED` | 429 | Trop de tentatives |
| `OTP_NOT_FOUND` | 404 | Code OTP introuvable |
| `INVALID_OTP` | 422 | Code invalide |
| `MAX_ATTEMPTS_EXCEEDED` | 422 | Nombre max de tentatives dépassé |
| `OTP_SEND_FAILED` | 500 | Échec de l'envoi |
| `OTP_RESEND_FAILED` | 500 | Échec du renvoi |
| `OTP_EXPIRED` | 422 | Code expiré |

### Utilisation dans un contrôleur

```php
public function verify(Request $request)
{
    $user = auth()->user();
    
    $response = $user->verifyOtp(
        code: $request->code,
        type: 'email_verification',
        destination: $user->email
    );
    
    if ($response->isSuccess()) {
        return redirect()->route('dashboard')
            ->with('success', $response->message);
    }
    
    // Adaptation du message selon le status
    $errorMessage = match($response->status->value) {
        'invalid_code' => 'Code incorrect',
        'expired_code' => 'Code expiré, veuillez en demander un nouveau',
        'max_attempts_exceeded' => 'Trop de tentatives, demandez un nouveau code',
        'rate_limited' => 'Trop de tentatives, réessayez plus tard',
        default => $response->message
    };
    
    return back()
        ->with('error', $errorMessage)
        ->with('remaining_attempts', $response->data['remaining_attempts'] ?? null);
}
```

---

## 🌍 Localisation et multi-langues

### Configuration des langues

```php
// config/otp.php
'localization' => [
    'locale' => env('OTP_LOCALE', 'fr'),
    'supported_locales' => ['fr', 'en'],
    'fallback_locale' => env('OTP_FALLBACK_LOCALE', 'en'),
],
```

```env
# .env
OTP_LOCALE=fr
OTP_FALLBACK_LOCALE=en
```

### Utilisation du helper `TranslationHelper`

```php
use Kani\Mfa\Helpers\TranslationHelper;

// Traduire un message
$message = TranslationHelper::trans('messages.send_success');
// → "Code de vérification envoyé avec succès." (en français)

// Avec placeholders
$message = TranslationHelper::trans('messages.expires_in', ['minutes' => 5]);
// → "Ce code expirera dans 5 minute(s)."
```

### Messages disponibles

| Clé | Description |
|-----|-------------|
| `messages.send_success` | Code envoyé avec succès |
| `messages.resend_success` | Code renvoyé avec succès |
| `messages.verify_success` | Code vérifié avec succès |
| `messages.send_failed` | Échec de l'envoi |
| `messages.resend_failed` | Échec du renvoi |
| `messages.otp_not_found` | Code introuvable |
| `messages.expired_code` | Code expiré |
| `messages.max_attempts_exceeded` | Trop de tentatives |
| `messages.invalid_code_attempts_remaining` | Code invalide, X tentatives restantes |
| `messages.rate_limited` | Trop de tentatives, patienter X secondes |
| `messages.subject` | Sujet de l'email |
| `messages.greeting` | Formule de salutation |
| `messages.intro` | Introduction de l'email |
| `messages.expires_in` | Message d'expiration |
| `messages.ignore_request` | Message si non demandé |
| `messages.salutation` | Formule de politesse |

### Ajouter une nouvelle langue

```bash
# 1. Créer le dossier
mkdir -p resources/lang/vendor/otp/es

# 2. Copier les fichiers depuis l'anglais
cp vendor/andydefer/laravel-otp/src/Lang/en/messages.php resources/lang/vendor/otp/es/

# 3. Traduire les valeurs
# resources/lang/vendor/otp/es/messages.php

# 4. Mettre à jour la configuration
'localization' => [
    'supported_locales' => ['fr', 'en', 'es'],
    // ...
]
```

---

## 🎨 Personnalisation

### Remplacer le générateur de code

**Étape 1 : Créer votre générateur**

```php
// app/Services/CustomCodeGenerator.php

namespace App\Services;

use Kani\Mfa\Contracts\CodeGeneratorInterface;

class CustomCodeGenerator implements CodeGeneratorInterface
{
    public function generate(): string
    {
        // Code alphanumérique de 8 caractères
        return substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
```

**Étape 2 : Enregistrer dans le service provider**

```php
// app/Providers/AppServiceProvider.php

use App\Services\CustomCodeGenerator;
use Kani\Mfa\Contracts\CodeGeneratorInterface;

public function register()
{
    $this->app->bind(CodeGeneratorInterface::class, CustomCodeGenerator::class);
}
```

### Remplacer le rate limiter

**Étape 1 : Créer votre rate limiter**

```php
// app/Services/RedisRateLimiter.php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Kani\Mfa\Contracts\RateLimiterInterface;

class RedisRateLimiter implements RateLimiterInterface
{
    public function isExceeded(string $key, int $maxAttempts): bool
    {
        return Redis::get($key) >= $maxAttempts;
    }
    
    public function hit(string $key, int $decaySeconds): void
    {
        Redis::incr($key);
        Redis::expire($key, $decaySeconds);
    }
    
    public function getAvailableInSeconds(string $key): int
    {
        return Redis::ttl($key);
    }
    
    public function clear(string $key): void
    {
        Redis::del($key);
    }
}
```

**Étape 2 : Enregistrer**

```php
$this->app->bind(RateLimiterInterface::class, RedisRateLimiter::class);
```

### Définir des canaux personnalisés avec `MustOtpChannels`

```php
// app/Models/User.php

use Kani\Mfa\Contracts\MustOtpChannels;

class User extends Authenticatable implements MustOtpChannels
{
    use HasOneTimePasswords;
    
    public function getOtpChannels(): array
    {
        $channels = ['mail'];
        
        if ($this->phone_verified_at) {
            $channels[] = 'sms';
        }
        
        if ($this->whatsapp_phone) {
            $channels[] = 'whatsapp';
        }
        
        return $channels;
    }
}
```

---

## 🖥️ Commandes Artisan

### `otp:install` - Installer le package

```bash
php artisan otp:install
php artisan otp:install --force          # Forcer l'écrasement
php artisan otp:install --no-migrate     # Ne pas exécuter les migrations
```

### `otp:cleanup` - Nettoyer les OTPs obsolètes

```bash
# Nettoyage basique (demande confirmation)
php artisan otp:cleanup

# Forcer sans confirmation
php artisan otp:cleanup --force

# Simulation (ne supprime rien)
php artisan otp:cleanup --dry-run

# Garder les codes expirés, supprimer seulement les anciens
php artisan otp:cleanup --keep-expired

# Supprimer les OTPs de plus de 15 jours
php artisan otp:cleanup --days=15

# Supprimer uniquement les OTPs d'un certain type
php artisan otp:cleanup --type=email_verification

# Combinaison d'options
php artisan otp:cleanup --force --type=login --days=7
```

**Exemple de sortie** :

```
🧹 Starting OTP cleanup...

⚠️  This will permanently delete expired and old OTPs. Do you wish to continue? (yes/no) [no]:
 > yes

🗄️ Running migrations...
   ✅ Migrations completed successfully.

═══════════════════════════════════════════════════════
🧹 OTP CLEANUP COMPLETED
═══════════════════════════════════════════════════════
┌─────────────────────────────┬───────┐
│ Metric                      │ Count │
├─────────────────────────────┼───────┤
│ Expired OTPs deleted        │ 12    │
│ Verified OTPs deleted       │ 5     │
│ Used OTPs deleted           │ 3     │
│ Cancelled OTPs deleted      │ 0     │
├─────────────────────────────┼───────┤
│ Total OTPs deleted          │ 20    │
└─────────────────────────────┴───────┘

✅ Cleanup completed successfully!

📋 Current Configuration:
   • Retention period: 30 days
   • Expired OTPs: ✅ Removed
```

---

## 🔧 Intégration avec d'autres canaux (SMS, WhatsApp)

### Créer une notification SMS personnalisée

```php
// app/Notifications/SmsOtpNotification.php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Kani\Mfa\Models\OneTimePassword;

class SmsOtpNotification extends Notification
{
    public function __construct(
        private OneTimePassword $otp,
        private string $plainCode
    ) {}

    public function via($notifiable): array
    {
        return ['vonage']; // ou 'twilio'
    }

    public function toVonage($notifiable)
    {
        $expiresIn = $this->otp->expires_at->diffInMinutes(now());
        
        return (new VonageMessage)
            ->content("Votre code OTP : {$this->plainCode}. Valable {$expiresIn} minutes.");
    }
}
```

### Étendre la notification principale

```php
// app/Notifications/CustomOtpNotification.php

namespace App\Notifications;

use Kani\Mfa\Notifications\OtpNotification as BaseOtpNotification;

class CustomOtpNotification extends BaseOtpNotification
{
    public function via($notifiable): array
    {
        // Déterminer les canaux dynamiquement
        $channels = ['mail'];
        
        if ($notifiable->hasPhone()) {
            $channels[] = 'vonage';
        }
        
        if ($notifiable->prefersWhatsApp()) {
            $channels[] = 'whatsapp';
        }
        
        return $channels;
    }
    
    public function toVonage($notifiable)
    {
        // Format SMS
        return (new VonageMessage)
            ->content("Code: {$this->plainCode}");
    }
    
    public function toWhatsApp($notifiable)
    {
        // Format WhatsApp
        return (new WhatsAppMessage)
            ->content("*Votre code* : {$this->plainCode}");
    }
}
```

### Enregistrer la notification personnalisée

```php
// config/otp.php
'notification' => App\Notifications\CustomOtpNotification::class,
```

---

## 🧪 Tests

### Tester l'envoi d'OTP

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_user_can_request_an_otp()
    {
        Notification::fake();
        
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post('/send-otp', ['type' => 'email_verification']);
        
        $response->assertStatus(200);
        $this->assertDatabaseHas('one_time_passwords', [
            'otpable_id' => $user->id,
            'type' => 'email_verification',
        ]);
    }
    
    public function test_user_can_verify_an_otp()
    {
        $user = User::factory()->create();
        
        // Créer un OTP en base
        $otp = $user->oneTimePasswords()->create([
            'token_hash' => Hash::make('123456'),
            'type' => 'login',
            'destination' => $user->email,
            'expires_at' => now()->addMinutes(10),
        ]);
        
        $response = $this->post('/verify-otp', [
            'code' => '123456',
            'type' => 'login',
            'destination' => $user->email
        ]);
        
        $response->assertStatus(200);
        $this->assertNotNull($otp->fresh()->verified_at);
    }
    
    public function test_resend_otp_cancels_old_one()
    {
        $user = User::factory()->create();
        
        // Premier OTP
        $firstOtp = $user->oneTimePasswords()->create([
            'token_hash' => Hash::make('123456'),
            'type' => 'login',
            'destination' => $user->email,
            'expires_at' => now()->addMinutes(10),
        ]);
        
        // Demande de renvoi
        $this->actingAs($user)->post('/resend-otp', [
            'type' => 'login',
            'destination' => $user->email
        ]);
        
        $firstOtp->refresh();
        $this->assertNotNull($firstOtp->cancelled_at);
        $this->assertEquals(2, $user->oneTimePasswords()->count());
    }
}
```

---

## 📝 Exemple complet : Vérification d'email

### Routes

```php
// routes/web.php

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', [EmailVerificationController::class, 'showForm'])
        ->name('verification.form');
    
    Route::post('/verify-email/send', [EmailVerificationController::class, 'sendCode'])
        ->name('verification.send');
    
    Route::post('/verify-email/verify', [EmailVerificationController::class, 'verifyCode'])
        ->name('verification.verify');
    
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resendCode'])
        ->name('verification.resend');
});
```

### Contrôleur

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function showForm()
    {
        $user = auth()->user();
        
        // Vérifier si un OTP est déjà en attente
        $hasPendingOtp = $user->hasValidOtp('email_verification', $user->email);
        
        return view('auth.verify-email', compact('hasPendingOtp'));
    }
    
    public function sendCode()
    {
        $user = auth()->user();
        
        $response = $user->sendOtp(
            type: 'email_verification',
            destination: $user->email,
            meta: ['ip' => request()->ip()]
        );
        
        if ($response->isSuccess()) {
            return redirect()->route('verification.form')
                ->with('success', 'Un code de vérification a été envoyé.');
        }
        
        return back()->with('error', $response->message);
    }
    
    public function verifyCode(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);
        
        $user = auth()->user();
        
        $response = $user->verifyOtp(
            code: $request->code,
            type: 'email_verification',
            destination: $user->email,
            consume: true
        );
        
        if ($response->isSuccess()) {
            $user->markEmailAsVerified();
            return redirect()->route('dashboard')
                ->with('success', 'Email vérifié avec succès !');
        }
        
        return back()
            ->with('error', $response->message)
            ->with('remaining_attempts', $response->data['remaining_attempts'] ?? null);
    }
    
    public function resendCode()
    {
        $user = auth()->user();
        
        $response = $user->resendOtp('email_verification', $user->email);
        
        if ($response->isSuccess()) {
            return back()->with('success', 'Un nouveau code a été envoyé.');
        }
        
        return back()->with('error', $response->message);
    }
}
```

### Vue Blade

```blade
{{-- resources/views/auth/verify-email.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Vérification d'email</div>
                
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    
                    <p>Un code de vérification a été envoyé à <strong>{{ auth()->user()->email }}</strong>.</p>
                    
                    <form method="POST" action="{{ route('verification.verify') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="code" class="form-label">Code de vérification</label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                   id="code" name="code" maxlength="6" required>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Vérifier</button>
                    </form>
                    
                    <hr>
                    
                    <form method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="btn btn-link w-100">
                            Renvoyer le code
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

---

## 🔗 Liens utiles

- [Dépôt GitHub](https://github.com/andydefer/laravel-otp)
- [Signalement de bugs](https://github.com/andydefer/laravel-otp/issues)
- [Documentation API](https://github.com/andydefer/laravel-otp/wiki)

---

## 📄 Licence

MIT © [Kani](https://github.com/andydefer)

---

**Laravel OTP** – La solution complète pour la gestion de mots de passe à usage unique dans Laravel, avec support multi-modèles, multi-langues, rate limiting, et nettoyage automatique. 🔐⚡
