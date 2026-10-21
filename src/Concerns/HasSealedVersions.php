<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Concerns;

use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Models\AuditEntry;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Relations\StringKeyedMorphMany;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Acceptance\Support\ModelKey;
use Victorio\Acceptance\Support\Sealer;

/**
 * Gives any Eloquent model sealed versions, whatever its key type (int, ULID or UUID):
 *
 *     use HasSealedVersions;
 *
 *     $version = $quote->seal(kind: 'quote', content: $publicContent, internal: $costs, by: $user);
 *     $version->verify();
 *     $quote->sealedVersions;           // newest first, current tenant only
 *     $quote->auditEntries;             // the timeline, newest first
 *
 * A model with sealed versions can be soft deleted, never deleted for good (evidence).
 *
 * @mixin Model
 */
trait HasSealedVersions
{
    /**
     * A subject with sealed versions cannot be deleted for good: soft deletes keep working, a
     * force delete (or the delete of a model without soft deletes) throws. Mass deletes below the
     * model events are not covered; the versions survive them anyway (no foreign key).
     */
    public static function bootHasSealedVersions(): void
    {
        static::deleting(static function (Model $subject): void {
            if (method_exists($subject, 'isForceDeleting') && ! $subject->isForceDeleting()) {
                return;
            }

            $model = AcceptanceConfig::versionModel();
            $sealed = $model::query()
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', ModelKey::of($subject))
                ->exists();

            if ($sealed) {
                throw EvidenceIsPermanent::make($subject, 'delete of a subject with sealed versions');
            }
        });
    }

    /**
     * Whether any version was ever sealed for this model, whatever the current tenant.
     */
    public function hasAnySealedVersion(): bool
    {
        $model = AcceptanceConfig::versionModel();

        return $model::query()
            ->where('subject_type', $this->getMorphClass())
            ->where('subject_id', ModelKey::of($this))
            ->exists();
    }

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

    /**
     * The timeline, newest first; with Filament tenancy on, only the current tenant's entries.
     *
     * @return StringKeyedMorphMany<AuditEntry, $this>
     */
    public function auditEntries(): StringKeyedMorphMany
    {
        $model = AcceptanceConfig::auditModel();
        $instance = new $model;
        $table = $instance->getTable();

        $relation = new StringKeyedMorphMany(
            $instance->newQuery()->forCurrentTenant(),
            $this,
            $table.'.subject_type',
            $table.'.subject_id',
            $this->getKeyName(),
        );

        $relation->getQuery()->orderByDesc($table.'.id');

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
