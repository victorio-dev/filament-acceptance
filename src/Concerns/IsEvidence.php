<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victorio\Acceptance\Builders\EvidenceBuilder;
use Victorio\Acceptance\Exceptions\EvidenceIsImmutable;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Support\Database\ModelKey;

/**
 * What makes a model evidence: written once, never changed or deleted through Eloquent, never
 * pruned, scoped to the current tenant on request. Used by SealedVersion and Decision; what it
 * covers and what it cannot is in design.md a, "What immutability covers".
 *
 * @mixin Model
 *
 * @internal
 */
trait IsEvidence
{
    public static function bootIsEvidence(): void
    {
        // The builder refuses mass writes; these cover the model paths that fire events.
        static::updating(static function (): never {
            throw EvidenceIsImmutable::make(static::class, 'save');
        });

        static::deleting(static function (): never {
            throw EvidenceIsPermanent::make(static::class, 'delete');
        });
    }

    /**
     * Refused outright: with nothing fillable, update() would otherwise discard the attributes
     * silently and report success.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw EvidenceIsImmutable::make($this, 'update');
    }

    /**
     * @param  QueryBuilder  $query
     * @return EvidenceBuilder<static>
     */
    public function newEloquentBuilder($query): EvidenceBuilder
    {
        /** @var EvidenceBuilder<static> $builder */
        $builder = new EvidenceBuilder($query);

        return $builder;
    }

    /**
     * With Filament tenancy on and a current tenant, only that tenant's rows (rule D9).
     *
     * @param  Builder<static>  $query
     */
    public function scopeForCurrentTenant(Builder $query): void
    {
        $tenant = AcceptanceConfig::tenancyEnabled() ? Filament::getTenant() : null;

        if ($tenant === null) {
            return;
        }

        $query->where($query->qualifyColumn(AcceptanceConfig::tenantColumn()), ModelKey::of($tenant));
    }
}
