<?php
// src/Otp/Repositories/OneTimePasswordRepository.php

declare(strict_types=1);

namespace AndyDefer\Mfa\Otp\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Mfa\Otp\Models\OneTimePassword;
use AndyDefer\Mfa\Otp\Records\OneTimePasswordFilterRecord;
use AndyDefer\Mfa\Otp\Records\OneTimePasswordRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OneTimePasswordRepository extends AbstractRepository
{
    private HydrationService $hydration;

    public function __construct()
    {
        parent::__construct(
            modelClass: OneTimePassword::class,
            recordClass: OneTimePasswordRecord::class,
        );
        $this->hydration = new HydrationService();
    }

    /**
     * @return Collection<int, OneTimePassword>
     */
    public function findByFilters(OneTimePasswordFilterRecord $filters, ?int $limit = null, ?string $sortBy = null, array $columns = ['*']): Collection
    {
        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: $limit,
            sortBy: $sortBy !== null ? new SortColumns($sortBy) : null,
            columns: new SelectColumns($columns)
        );

        return $this->findBy($findByRecord);
    }

    /**
     * Récupère le dernier OTP créé pour un otpable donné
     * 
     * @param Model $otpable Le modèle (User, etc.) pour lequel récupérer le dernier OTP
     * @return OneTimePassword|null
     */
    public function findLatestOtp(Model $otpable): ?OneTimePassword
    {
        $filter = $this->hydration->hydrate(OneTimePasswordFilterRecord::class, [
            'otpable_type' => $otpable->getMorphClass(),
            'otpable_id' => $otpable->getKey(),
        ]);

        $findByRecord = new FindByRecord(
            filters: $filter,
            limit: 1,
            sortBy: new SortColumns('created_at:desc|id:desc'),
        );

        $collection = $this->findBy($findByRecord);

        return $collection->first();
    }

    /**
     * Récupère tous les OTPs actifs (non vérifiés, non utilisés, non annulés, non expirés)
     * 
     * @param Model $otpable Le modèle (User, etc.) pour lequel récupérer les OTPs actifs
     * @return Collection<int, OneTimePassword>
     */
    public function findActiveOtps(Model $otpable): Collection
    {
        $filter = $this->hydration->hydrate(OneTimePasswordFilterRecord::class, [
            'otpable_type' => $otpable->getMorphClass(),
            'otpable_id' => $otpable->getKey(),
            'is_verified' => false,
            'is_used' => false,
            'is_cancelled' => false,
            'is_expired' => false,
        ]);

        $findByRecord = new FindByRecord(filters: $filter);

        return $this->findBy($findByRecord);
    }

    /**
     * Trouve un OTP en attente (non vérifié, non utilisé, non annulé, non expiré)
     * 
     * @param Model $otpable Le modèle (User, etc.) associé à l'OTP
     * @param string $type Le type d'OTP
     * @param string $destination La destination (email, téléphone, etc.)
     * @return OneTimePassword|null
     */
    public function findPendingOtp(Model $otpable, string $type, string $destination): ?OneTimePassword
    {
        $filters = $this->hydration->hydrate(OneTimePasswordFilterRecord::class, [
            'type' => $type,
            'destination' => $destination,
            'otpable_id' => $otpable->getKey(),
            'otpable_type' => $otpable->getMorphClass(),
            'is_verified' => false,
            'is_used' => false,
            'is_cancelled' => false,
            'is_expired' => false,
        ]);

        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1,
            sortBy: new SortColumns('created_at:desc|id:desc'),
        );

        $collection = $this->findBy($findByRecord);

        return $collection->first();
    }

    /**
     * Trouve un OTP valide pour vérification
     * Ne filtre PAS sur is_used et is_verified pour permettre 
     * de trouver les OTPs déjà utilisés/vérifiés et les traiter dans la logique métier
     * 
     * @param Model $otpable Le modèle (User, etc.) associé à l'OTP
     * @param string $type Le type d'OTP
     * @param string $destination La destination (email, téléphone, etc.)
     * @return OneTimePassword|null
     */
    public function findValidOtpForVerification(Model $otpable, string $type, string $destination): ?OneTimePassword
    {
        $filters = $this->hydration->hydrate(OneTimePasswordFilterRecord::class, [
            'type' => $type,
            'destination' => $destination,
            'otpable_id' => $otpable->getKey(),
            'otpable_type' => $otpable->getMorphClass(),
            'is_cancelled' => false,
        ]);

        $findByRecord = new FindByRecord(
            filters: $filters,
            limit: 1,
            sortBy: new SortColumns('created_at:desc|id:desc'),
        );

        $collection = $this->findBy($findByRecord);

        return $collection->first();
    }

    /**
     * Supprime physiquement les anciens OTPs en attente (utilisé par send())
     * 
     * @param Model $otpable Le modèle (User, etc.) associé à l'OTP
     * @param string $type Le type d'OTP
     * @param string $destination La destination (email, téléphone, etc.)
     * @return int Nombre d'OTPs supprimés
     */
    public function deleteOldPendingOtps(Model $otpable, string $type, string $destination): int
    {
        $filters = $this->hydration->hydrate(OneTimePasswordFilterRecord::class, [
            'type' => $type,
            'destination' => $destination,
            'otpable_id' => $otpable->getKey(),
            'otpable_type' => $otpable->getMorphClass(),
            'is_verified' => false,
            'is_used' => false,
        ]);

        return $this->deleteBulk($filters);
    }

    /**
     * Annulation logique des OTPs en attente (utilisé par cancel())
     * 
     * @param Model $otpable Le modèle (User, etc.) associé à l'OTP
     * @param string $type Le type d'OTP
     * @param string $destination La destination (email, téléphone, etc.)
     * @return int Nombre d'OTPs annulés
     */
    public function cancelPendingOtps(Model $otpable, string $type, string $destination): int
    {
        $filters = $this->hydration->hydrate(OneTimePasswordFilterRecord::class, [
            'type' => $type,
            'destination' => $destination,
            'otpable_id' => $otpable->getKey(),
            'otpable_type' => $otpable->getMorphClass(),
            'is_verified' => false,
            'is_used' => false,
            'is_cancelled' => false,
        ]);

        $models = $this->findByFilters($filters);
        $count = 0;

        foreach ($models as $model) {
            $model->cancelled_at = now();
            $model->save();
            $count++;
        }

        return $count;
    }

    /**
     * Annule les OTPs expirés
     * 
     * @return int Nombre d'OTPs annulés
     */
    public function cancelExpiredOtps(): int
    {
        $filters = $this->hydration->hydrate(OneTimePasswordFilterRecord::class, [
            'is_expired' => true,
            'is_verified' => false,
            'is_used' => false,
            'is_cancelled' => false,
        ]);

        $models = $this->findByFilters($filters);

        foreach ($models as $model) {
            $model->cancelled_at = now();
            $model->save();
        }

        return $models->count();
    }

    /**
     * Crée un nouvel OTP
     * Force la génération d'un nouvel UUID à chaque création
     * 
     * @param array $data Les données de l'OTP
     * @return OneTimePassword
     */
    public function createRaw(array $data): Model
    {
        $data['id'] = (string) Str::uuid();

        return parent::createRaw($data);
    }

    /**
     * Applique les filtres à la requête
     */
    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (!$filters instanceof OneTimePasswordFilterRecord) {
            return;
        }

        if ($filters->id !== null) {
            $query->where('id', $filters->id);
        }

        if ($filters->otpable_type !== null) {
            $query->where('otpable_type', $filters->otpable_type);
        }

        if ($filters->otpable_id !== null) {
            $query->where('otpable_id', $filters->otpable_id);
        }

        if ($filters->token_hash !== null) {
            $query->where('token_hash', $filters->token_hash);
        }

        if ($filters->type !== null) {
            $query->where('type', $filters->type);
        }

        if ($filters->destination !== null) {
            $query->where('destination', $filters->destination);
        }

        // Gestion des OTPs expirés
        if ($filters->is_expired === true) {
            $query->whereNotNull('expires_at')->where('expires_at', '<', now());
        } elseif ($filters->is_expired === false) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
        }

        // Gestion des OTPs vérifiés
        if ($filters->is_verified === true) {
            $query->whereNotNull('verified_at');
        } elseif ($filters->is_verified === false) {
            $query->whereNull('verified_at');
        }

        // Gestion des OTPs utilisés
        if ($filters->is_used === true) {
            $query->whereNotNull('used_at');
        } elseif ($filters->is_used === false) {
            $query->whereNull('used_at');
        }

        // Gestion des OTPs annulés
        if ($filters->is_cancelled === true) {
            $query->whereNotNull('cancelled_at');
        } elseif ($filters->is_cancelled === false) {
            $query->whereNull('cancelled_at');
        }

        // Filtres de date
        if ($filters->created_before !== null) {
            $query->where('created_at', '<', $filters->created_before->toDateTimeString());
        }

        if ($filters->created_after !== null) {
            $query->where('created_at', '>', $filters->created_after->toDateTimeString());
        }

        if ($filters->expires_before !== null) {
            $query->where('expires_at', '<', $filters->expires_before->toDateTimeString());
        }

        if ($filters->expires_after !== null) {
            $query->where('expires_at', '>', $filters->expires_after->toDateTimeString());
        }
    }
}
