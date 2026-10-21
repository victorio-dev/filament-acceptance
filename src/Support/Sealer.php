<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Victorio\Acceptance\Events\VersionSealed;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Support\Database\ModelKey;

/**
 * Seals a subject's content as its next version (design.md b, "Sealing").
 *
 * @internal
 */
final class Sealer
{
    /**
     * @param  array<array-key, mixed>  $content
     * @param  array<array-key, mixed>|null  $internal
     */
    public function seal(Model $subject, string $kind, array $content, ?array $internal, ?Model $by, ?Model $tenant): SealedVersion
    {
        if (! $subject->exists) {
            throw new InvalidArgumentException('Save the subject before sealing it.');
        }

        // Fail before the transaction: a float or an object never reaches the database.
        $canonical = CanonicalJson::encode($content);
        $encryptedInternal = $internal === null
            ? null
            : Crypt::encryptString(json_encode($internal, JSON_THROW_ON_ERROR));
        $tenantKey = $this->tenantKey($tenant);

        $model = AcceptanceConfig::versionModel();
        $connection = (new $model)->getConnection();

        $version = $connection->transaction(function () use ($subject, $kind, $canonical, $encryptedInternal, $by, $tenantKey, $model): SealedVersion {
            // Serialises concurrent seals of the same subject; the unique index on
            // (subject_type, subject_id, number) is the backstop.
            $subject->newQueryWithoutScopes()->whereKey($subject->getKey())->lockForUpdate()->first();

            $subjectType = $subject->getMorphClass();
            $subjectId = ModelKey::of($subject);
            $previous = $model::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->max('number');
            $number = (is_numeric($previous) ? (int) $previous : 0) + 1;
            $now = now();

            $version = new $model;
            $version->forceFill([
                AcceptanceConfig::tenantColumn() => $tenantKey,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'kind' => $kind,
                'number' => $number,
                'content' => $canonical,
                'content_hash' => hash('sha256', $canonical),
                'internal' => $encryptedInternal,
                'sealed_by_type' => $by?->getMorphClass(),
                'sealed_by_id' => $by === null ? null : ModelKey::of($by),
                'sealed_at' => $now,
            ])->save();

            // The one write a sealed row ever gets, below Eloquent on purpose: the builder refuses
            // updates, and superseded_at is not part of the sealed content.
            $version->newQuery()->toBase()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->where('number', '<', $number)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => $now]);

            app(AuditLogger::class)->recordForVersion($version, 'sealed', causer: $by, properties: [
                'kind' => $kind,
                'number' => $number,
                'content_hash' => $version->content_hash,
            ]);

            return $version;
        });

        event(new VersionSealed($version));

        return $version;
    }

    private function tenantKey(?Model $tenant): ?string
    {
        if (! AcceptanceConfig::tenancyEnabled()) {
            return null;
        }

        $tenant ??= Filament::getTenant();

        return $tenant === null ? null : ModelKey::of($tenant);
    }
}
