# Nemesis — Authentification par tokens multi-modèles pour Laravel

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-12%2B-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Tests](https://img.shields.io/badge/tests-2500%20passing-brightgreen)
![Coverage](https://img.shields.io/badge/coverage-92%25-green)

**Nemesis** est un package Laravel complet pour l’authentification par **tokens multi-modèles**. Contrairement à Sanctum ou Passport, Nemesis permet à **n’importe quel modèle Eloquent** (`User`, `CheckPoint`, `ApiClient`, `Admin`, etc.) de générer, valider et gérer ses propres tokens d’API avec une sécurité renforcée : expiration, permissions (abilities), restrictions CORS par origine, métadonnées, soft delete pour révocation, et nettoyage automatique.

---

## 📦 Installation

```bash
composer require andydefer/laravel-nemesis
```

Publier les ressources du package :

```bash
php artisan nemesis:install
```

Ou manuellement :

```bash
# Configuration
php artisan vendor:publish --tag=nemesis-config

# Migrations
php artisan vendor:publish --tag=nemesis-migrations

# Exécuter les migrations
php artisan migrate
```

---

## 🚀 Démarrage rapide

### 1. Ajouter le trait et l’interface à vos modèles

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kani\Otp\Contracts\MustNemesis;
use Kani\Otp\Traits\HasNemesisTokens;

class User extends Model implements MustNemesis
{
    use HasNemesisTokens;

    /**
     * Définir ce qui est exposé par l'API.
     * Cette méthode est OBLIGATOIRE (imposée par l'interface).
     */
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

class CheckPoint extends Model implements MustNemesis
{
    use HasNemesisTokens;

    /**
     * Format différent pour les points de contrôle.
     */
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'status' => $this->is_active ? 'active' : 'inactive',
            'last_seen' => $this->last_ping_at?->toIso8601String(),
        ];
    }
}
```

### 2. Créer un token

```php
$user = User::find(1);
$token = $user->createNemesisToken(
    name: 'Application Mobile',
    source: 'mobile',
    abilities: ['scan_ticket', 'view_stats'],
    metadata: ['app_version' => '2.1.0']
);

// Afficher le token une seule fois
echo $token; // stocker en clair côté client
```

### 3. Protéger une route

```php
// Dans routes/api.php
Route::middleware(['nemesis.auth'])->group(function () {
    Route::get('/profile', function () {
        // Version formatée (recommandée)
        return response()->json(current_authenticatable_format());
    });
});

// Avec vérification d’une ability
Route::post('/scan', function () {
    // ...
})->middleware('nemesis.auth:scan_ticket');
```

### 4. Utiliser le token

```http
GET /api/profile
Authorization: Bearer <token>
```

### 5. Gérer les tokens dans le contrôleur

```php
public function revokeCurrentToken(Request $request)
{
    $authenticatable = current_authenticatable();
    
    // La méthode retourne un booléen indiquant le succès
    if ($authenticatable->revokeCurrentNemesisToken()) {
        return response()->json(['message' => 'Token révoqué avec succès']);
    }
    
    return response()->json(['error' => 'Aucun token actif trouvé'], 404);
}
```

---

## 🎨 Contrôle total de l’exposition des données (nemesisFormat)

Nemesis **impose** à chaque modèle authentifiable de définir sa propre méthode `nemesisFormat()`. Cela force les développeurs à explicitement choisir quelles données sont exposées via l’API, évitant ainsi les fuites accidentelles d’informations sensibles.

### ❌ Sans Nemesis (dangereux)

```php
// Expose TOUT (password, remember_token, etc.)
return response()->json(auth()->user());
```

### ✅ Avec Nemesis (sécurisé)

```php
// N'expose que ce qui est défini dans nemesisFormat()
return response()->json(current_authenticatable_format());
```

### Exemple concret

```php
// Modèle User
public function nemesisFormat(): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'avatar' => $this->avatar_url,
        'roles' => $this->roles->pluck('name'),
    ];
}

// Modèle CheckPoint
public function nemesisFormat(): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'location' => $this->location,
        'status' => $this->is_active ? 'active' : 'inactive',
        'type' => $this->type,
    ];
}
```

---

## 🛡️ Sécurité multi-origines (CORS)

Nemesis permet de restreindre un token à des origines spécifiques, y compris avec des wildcards.

```php
$tokenModel = $user->getNemesisToken($plainToken);
$tokenModel->addAllowedOrigin('https://monapp.com');
$tokenModel->addAllowedOrigin('https://*.example.com'); // wildcard

// Vérification automatique dans le middleware
// Si l’origine n’est pas autorisée → erreur 403
```

---

## 🔑 Système d’abilities (permissions fines)

Chaque token peut avoir une liste d’abilities (ex: `create`, `delete`, `scan_ticket`).

```php
// Création avec abilities
$token = $checkpoint->createNemesisToken(
    name: 'Scanner Billeterie',
    source: 'kiosk',
    abilities: ['scan_ticket', 'validate_entry']
);

// Vérifier une ability
if ($tokenModel->can('scan_ticket')) {
    // autorisé
}
```

Utilisation en middleware :
```php
Route::post('/validate', fn() => ...)
    ->middleware('nemesis.auth:validate_entry');
```

---

## 📦 Métadonnées enrichies

Stockez des informations contextuelles (IP, user-agent, version, etc.) avec validation automatique (taille max 64KB, profondeur max 5, max 100 clés).

```php
$token = $user->createNemesisToken(
    name: 'API Session',
    metadata: [
        'device' => 'iPhone 15',
        'os' => 'iOS 17',
        'location' => 'Paris',
        'preferences' => ['lang' => 'fr']
    ]
);

// Modifier après création
$tokenModel->setMetadata('last_login_ip', '192.168.1.1');
$ip = $tokenModel->getMetadata('last_login_ip');
$tokenModel->mergeMetadata(['new_key' => 'value']);
```

---

## 🧹 Nettoyage automatique des tokens expirés

Configuration dans `config/nemesis.php` :

```php
'cleanup' => [
    'auto_cleanup' => true,       // nettoyage auto par schedule
    'frequency' => 60,            // toutes les heures
    'keep_expired_for_days' => 30, // garder 30 jours pour audit
],
```

Commande manuelle :
```bash
php artisan nemesis:clean --force
php artisan nemesis:clean --days=15
php artisan nemesis:clean --keep-expired
```

---

## 📋 Commandes disponibles

| Commande | Description |
|----------|-------------|
| `nemesis:install` | Publie config + migrations |
| `nemesis:clean`   | Supprime tokens expirés/vieux |
| `nemesis:list`    | Liste tous les tokens (filtrable par modèle) |

```bash
php artisan nemesis:list --model=App\\Models\\CheckPoint
```

---

## 🧪 Helpers globaux

Nemesis fournit des helpers pour un accès rapide :

```php
// Récupérer le manager
nemesis()->validateToken($user, $token);

// Token actuel
$tokenModel = current_token();
if ($tokenModel && $tokenModel->can('admin')) {
    // ...
}

// Modèle authentifié brut (User, CheckPoint, etc.)
$authenticated = current_authenticatable();

// Version formatée du modèle authentifié (recommandée pour les APIs)
$formatted = current_authenticatable_format();
return response()->json($formatted);
```

---

## 🔗 Scénario concret : Billeterie avec User et CheckPoint

### Modèles

```php
// User (client billetterie)
class User extends Model implements MustNemesis
{
    use HasNemesisTokens;
    
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

// CheckPoint (point de contrôle physique)
class CheckPoint extends Model implements MustNemesis
{
    use HasNemesisTokens;
    
    public function nemesisFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'status' => $this->is_active ? 'active' : 'inactive',
        ];
    }
}
```

### Création des tokens

```php
// Pour un utilisateur (application mobile)
$userToken = $user->createNemesisToken(
    name: 'App Mobile Client',
    source: 'mobile',
    abilities: ['buy_ticket', 'view_tickets']
);

// Pour un point de contrôle (kiosque)
$checkpointToken = $checkpoint->createNemesisToken(
    name: 'Scanner Portique',
    source: 'kiosk',
    abilities: ['scan_ticket', 'validate_entry', 'reject_entry'],
    metadata: ['hardware_id' => 'SCAN-01', 'location' => 'Entrée A']
);
```

### Routes protégées

```php
// Endpoint utilisateur
Route::middleware('nemesis.auth:buy_ticket')->post('/tickets', [TicketController::class, 'buy']);

// Endpoint point de contrôle
Route::middleware('nemesis.auth:scan_ticket')->post('/scan', [ScanController::class, 'validate']);
```

### Dans `ScanController`

```php
public function validate(Request $request)
{
    $checkpoint = current_authenticatable(); // instance de CheckPoint
    $token = current_token();

    if (!$token->can('validate_entry')) {
        return response()->json(['error' => 'Permission refusée'], 403);
    }

    // scanner le billet...
    return response()->json([
        'status' => 'entrée validée',
        'checkpoint' => current_authenticatable_format() // version formatée
    ]);
}
```

### Révocation depuis le point de contrôle

```php
public function logoutCheckPoint()
{
    $checkpoint = current_authenticatable();
    
    // La méthode retourne un booléen indiquant si la révocation a réussi
    if ($checkpoint->revokeCurrentNemesisToken()) {
        return response()->json(['message' => 'Token révoqué avec succès']);
    }
    
    return response()->json(['error' => 'Aucun token actif trouvé'], 404);
}
```

---

## 📊 API complète du modèle (MustNemesis)

| Méthode | Description | Retour |
|---------|-------------|--------|
| `nemesisFormat()` | **OBLIGATOIRE** - Définit les données exposées par l'API | `array` |
| `createNemesisToken()` | Génère un nouveau token (hash stocké) | `string` |
| `deleteNemesisTokens()` | Suppression définitive de tous les tokens | `int` |
| `revokeNemesisTokens()` | Soft delete de tous les tokens | `int` |
| `deleteCurrentNemesisToken()` | Supprime définitivement le token courant | `bool` |
| `revokeCurrentNemesisToken()` | Soft delete du token courant | `bool` |
| `currentNemesisToken()` | Récupère le modèle du token courant | `?NemesisToken` |
| `hasNemesisTokens()` | Vérifie l’existence de tokens | `bool` |
| `getNemesisToken()` | Trouve un token par sa valeur brute | `?NemesisToken` |
| `validateNemesisToken()` | Vérifie validité (expiration + non révoqué) | `bool` |
| `touchNemesisToken()` | Met à jour `last_used_at` | `bool` |
| `getNemesisTokensBySource()` | Filtre par source (`web`, `mobile`, etc.) | `iterable` |
| `revokeExpiredNemesisTokens()` | Soft delete des expirés | `int` |
| `forceDeleteExpiredNemesisTokens()` | Suppression définitive des expirés | `int` |
| `restoreNemesisTokens()` | Restaure les tokens soft-deleted | `int` |

---

## 🧰 NemesisManager (facade)

```php
use Kani\Otp\Facades\Nemesis;

Nemesis::createToken($user, 'API Token', 'api', ['read']);
Nemesis::validateToken($user, $token);
Nemesis::getTokenableModel($token);
Nemesis::deleteToken($user, $token);
Nemesis::revokeExpiredTokens();
```

---

## ⚙️ Configuration (`config/nemesis.php`)

```php
return [
    'token_length' => 64,               // longueur du token en clair
    'hash_algorithm' => 'sha256',       // hash pour stockage
    'expiration' => 60,                 // null = jamais, sinon minutes

    'middleware' => [
        'parameter_name' => 'nemesisAuth',    // nom dans la requête
        'token_header' => 'Authorization',    // ou X-Custom-Token
        'security_headers' => true,           // X-Frame-Options, etc.
        'validate_origin' => true,            // vérification CORS
    ],

    'cors' => [
        'allow_credentials' => true,
        'max_age' => 86400,
        'expose_token_info' => false,
    ],

    'cleanup' => [
        'auto_cleanup' => true,
        'frequency' => 60,                     // minutes
        'keep_expired_for_days' => 30,
    ],
];
```

---

## 📁 Structure des migrations

```php
// Table nemesis_tokens
- id
- token_hash (unique)
- tokenable_type / tokenable_id (polymorphique)
- name, source
- abilities (JSON)
- metadata (JSON)
- allowed_origins (JSON)
- last_used_at, expires_at
- softDeletes, timestamps
```

---

## 🧠 Ce que Nemesis résout concrètement

| Problème | Solution Nemesis |
|----------|------------------|
| Plusieurs modèles doivent s’authentifier (User, CheckPoint) | Polymorphisme `tokenable` |
| Contrôle total des données exposées via API | Méthode obligatoire `nemesisFormat()` |
| Un token ne doit servir que pour certaines origines | `allowed_origins` + validation middleware |
| Une application mobile a moins de droits qu’un admin | `abilities` (ex: `scan_ticket` vs `delete_user`) |
| Besoin de tracer le contexte (IP, device, version) | `metadata` validé et nettoyé |
| Révocation sans perte d’audit | `softDeletes` |
| Nettoyage des tokens obsolètes | Commande schedule + `auto_cleanup` |
| Un token peut expirer après X minutes | `expires_at` + `isExpired()` |
| Savoir si une opération a réussi (suppression/révocation) | Retour `bool` des méthodes concernées |

---

## 🔄 Comparaison rapide avec Laravel Sanctum

| Fonctionnalité | Sanctum | Nemesis |
|----------------|---------|---------|
| Multi-modèles (User + CheckPoint) | ❌ (seulement User) | ✅ (tout modèle) |
| Contrôle explicite de l'exposition des données | ❌ | ✅ (méthode obligatoire) |
| Restrictions CORS par token | ❌ (globale) | ✅ (par token) |
| Métadonnées enrichies | ❌ | ✅ (validation stricte) |
| Soft delete des tokens | ❌ | ✅ |
| Abilities sans user | ❌ | ✅ |
| Nettoyage auto configurable | ❌ | ✅ |
| Retour booléen sur les opérations de suppression | ❌ | ✅ |

---

## 🤝 Contribution

1. Fork + branche `feature/ma-fonctionnalité`
2. `composer test` (2500 tests doivent passer)
3. Pull request vers `main`

---

## 📄 Licence

MIT © [Kani](https://github.com/kani)

---

**Nemesis** – L’authentification par tokens multi-modèles pour Laravel, pensée pour les systèmes complexes où chaque acteur (utilisateur, point de contrôle, API client) a ses propres jetons, droits et origines, avec un **contrôle total sur les données exposées** et des **retours explicites sur les opérations critiques**. 🔐⚡
