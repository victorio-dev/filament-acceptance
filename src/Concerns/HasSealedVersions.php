<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Concerns;

use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Relations\StringKeyedMorphMany;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Acceptance\Support\Sealer;

/**
 * Gives any Eloquent model sealed versions, whatever its key type (int, ULID or UUID):
 *
 *     use HasSealedVersions;
 *
 *     $version = $quote->seal(kind: 'quote', content: $publicContent, internal: $costs, by: $user);
 *     $version->verify();
 *     $quote->sealedVersions;           // newest first, current tenant only
 *
 * @mixin Model
 */
trait HasSealedVersions
{
    /**
     * Newest first; with Filament tenancy on, only the current tenant's versions.
     *
     * @return StringKeyedMorphMany<SealedVersion, $this>
     */
    public function sealedVersions(): StringKeyedMorphMany
    {
        $model = AcceptanceConfig::versionModel();
        $instance = new $model;
        $table = $instance->getTable();

        $relation = new StringKeyedMorphMany(
            $instance->newQuery()->forCurrentTenant(),
            $this,
            $table.'.subject_type',
            $table.'.subject_id',
            $this->getKeyName(),
        );

        $relation->getQuery()->orderByDesc($table.'.number');

        return $relation;
    }

    public function latestSealedVersion(): ?SealedVersion
    {
        return $this->sealedVersions()->first();
    }

    /**
     * Seals the content as this model's next version. The content is stored in canonical JSON
     * with its SHA-256 hash; the internal part is encrypted with the app key and not hashed.
     * The tenant is the current Filament tenant unless one is passed (e.g. in a queued job).
     *
     * @param  array<array-key, mixed>  $content
     * @param  array<array-key, mixed>|null  $internal
     */
    public function seal(string $kind, array $content, ?array $internal = null, ?Model $by = null, ?Model $tenant = null): SealedVersion
    {
        return app(Sealer::class)->seal($this, $kind, $content, $internal, $by, $tenant);
    }
}
