<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Victorio\Acceptance\Exceptions\AuditPropertyNotAllowed;
use Victorio\Acceptance\Models\AuditEntry;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Support\IpHasher;

/**
 * Writes the audit log (design.md b, "Audit log"). The package writes `sealed`, `accepted`,
 * `declined`, `evidence_stored` and `erasure` itself; consumers add their own events.
 *
 * @internal
 */
final class AuditLogger
{
    /**
     * Keys refused anywhere in the properties: personal data, IPs and secrets (rule E5).
     */
    private const array FORBIDDEN_KEYS = ['ip', 'ip_address', 'email', 'name', 'phone', 'token', 'password', 'secret'];

    /**
     * @param  array<string, mixed>  $properties
     *
     * @throws AuditPropertyNotAllowed
     */
    public function record(
        Model $subject,
        string $event,
        ?Model $causer = null,
        ?SealedVersion $version = null,
        array $properties = [],
        ?Request $request = null,
        ?Model $tenant = null,
    ): AuditEntry {
        return $this->write($subject->getMorphClass(), ModelKey::of($subject), $event, $causer, $version, $properties, $request, $tenant);
    }

    /**
     * For a version's own events: its subject may be soft-deleted, so it is named by type and id.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordForVersion(SealedVersion $version, string $event, ?Model $causer = null, array $properties = [], ?Request $request = null): AuditEntry
    {
        return $this->write($version->subject_type, $version->subject_id, $event, $causer, $version, $properties, $request, null);
    }

    /**
     * The record an erasure leaves behind, after the versions it names are gone.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordErasure(string $subjectType, string $subjectId, ?string $tenantKey, ?Model $causer, array $properties): AuditEntry
    {
        return $this->write($subjectType, $subjectId, AuditEntry::ERASURE, $causer, null, $properties, null, $tenantKey);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function write(
        string $subjectType,
        string $subjectId,
        string $event,
        ?Model $causer,
        ?SealedVersion $version,
        array $properties,
        ?Request $request,
        Model|string|null $tenant,
    ): AuditEntry {
        self::guard($properties, '');

        $model = AcceptanceConfig::auditModel();
        $tenantColumn = AcceptanceConfig::tenantColumn();

        $entry = new $model;
        $entry->forceFill([
            $tenantColumn => match (true) {
                $version !== null => $version->getAttribute($tenantColumn),
                is_string($tenant) => $tenant,
                default => $this->tenantKey($tenant),
            },
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'sealed_version_id' => $version?->getKey(),
            'event' => $event,
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer === null ? null : ModelKey::of($causer),
            // The same HMAC as the filament-public-links access log of this request.
            'ip_hash' => $request === null ? null : IpHasher::forRequest($request),
            'properties' => $properties === [] ? null : $properties,
            'occurred_at' => now(),
        ])->save();

        return $entry;
    }

    /**
     * @param  array<array-key, mixed>  $properties
     */
    private static function guard(array $properties, string $path): void
    {
        foreach ($properties as $key => $value) {
            $keyPath = $path === '' ? (string) $key : $path.'.'.$key;

            if (in_array(strtolower((string) $key), self::FORBIDDEN_KEYS, true)) {
                throw AuditPropertyNotAllowed::key($keyPath);
            }

            if (is_array($value)) {
                self::guard($value, $keyPath);
            }
        }
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
