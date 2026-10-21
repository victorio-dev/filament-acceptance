<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;
use Victorio\Acceptance\Data\ErasureReport;
use Victorio\Acceptance\Models\AuditEntry;

/**
 * The one deliberate way to remove evidence (design.md a, "Retention"). Nothing in the package
 * calls it: not pruning, not a model delete. Both modes leave an erasure record in the audit log.
 *
 * - anonymize: removes the personal data of the decisions (name, e-mail, comment, reason, user
 *   agent, IP hash) and the IP hashes of the audit log. Sealed content, hashes and evidence files
 *   stay, so verify() keeps its result. Personal data inside the sealed content stays too.
 * - purge: removes the subject's sealed versions, decisions, evidence files and audit entries.
 *
 * Writes go below Eloquent on purpose: the models refuse every change.
 *
 * @internal
 */
final class Eraser
{
    public function anonymize(string $subjectType, string $subjectId, string $reason, ?Model $by): ErasureReport
    {
        $reason = self::reason($reason);

        return DB::connection($this->connectionName())->transaction(function () use ($subjectType, $subjectId, $reason, $by): ErasureReport {
            $versionIds = $this->versionIds($subjectType, $subjectId);

            $decisions = DB::connection($this->connectionName())->table(AcceptanceConfig::decisionsTable())
                ->whereIn('sealed_version_id', $versionIds)
                ->update([
                    'name' => null,
                    'email' => null,
                    'comment' => null,
                    'reason' => null,
                    'user_agent' => null,
                    'ip_hash' => null,
                ]);

            $entries = $this->auditEntries($subjectType, $subjectId)
                ->whereNotNull('ip_hash')
                ->update(['ip_hash' => null]);

            return $this->report('anonymize', $subjectType, $subjectId, $reason, $by, 0, $decisions, 0, $entries, [], $this->tenantKey($subjectType, $subjectId));
        });
    }

    public function purge(string $subjectType, string $subjectId, string $reason, ?Model $by): ErasureReport
    {
        $reason = self::reason($reason);
        $files = [];

        $report = DB::connection($this->connectionName())->transaction(function () use ($subjectType, $subjectId, $reason, $by, &$files): ErasureReport {
            $connection = DB::connection($this->connectionName());
            $tenantKey = $this->tenantKey($subjectType, $subjectId);
            $versions = $connection->table(AcceptanceConfig::versionsTable())
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->orderBy('number')
                ->get(['id', 'number', 'content_hash']);
            $versionIds = $versions->pluck('id')->all();

            $decisions = $connection->table(AcceptanceConfig::decisionsTable())->whereIn('sealed_version_id', $versionIds);
            $files = $decisions->clone()->whereNotNull('evidence_path')->get(['evidence_disk', 'evidence_path'])->all();
            $decisionCount = $decisions->delete();

            // Earlier erasure records stay: they are the history of what was removed.
            $entries = $this->auditEntries($subjectType, $subjectId)->where('event', '!=', AuditEntry::ERASURE)->delete();
            $versionCount = $connection->table(AcceptanceConfig::versionsTable())->whereIn('id', $versionIds)->delete();

            return $this->report('purge', $subjectType, $subjectId, $reason, $by, $versionCount, $decisionCount, count($files), $entries, [
                // Numbers and hashes only, so the record names what was removed without its content.
                'removed_versions' => $versions->map(static fn (object $version): array => [
                    'number' => is_numeric($version->number ?? null) ? (int) $version->number : 0,
                    'content_hash' => is_string($version->content_hash ?? null) ? $version->content_hash : '',
                ])->values()->all(),
            ], $tenantKey);
        });

        // Files go once the rows are gone for good; a file that cannot be removed is logged.
        foreach ($files as $file) {
            try {
                Storage::disk((string) $file->evidence_disk)->delete((string) $file->evidence_path);
            } catch (Throwable $exception) {
                Log::warning('filament-acceptance: an erased evidence file could not be deleted.', [
                    'disk' => $file->evidence_disk,
                    'path' => $file->evidence_path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function report(
        string $mode,
        string $subjectType,
        string $subjectId,
        string $reason,
        ?Model $by,
        int $versions,
        int $decisions,
        int $evidenceFiles,
        int $auditEntries,
        array $extra,
        ?string $tenantKey,
    ): ErasureReport {
        $record = app(AuditLogger::class)->recordErasure($subjectType, $subjectId, $tenantKey, $by, [
            'mode' => $mode,
            'reason' => $reason,
            'versions' => $versions,
            'decisions' => $decisions,
            'evidence_files' => $evidenceFiles,
            'audit_entries' => $auditEntries,
            ...$extra,
        ]);

        // Ids and counts only (rule E5).
        Log::notice('filament-acceptance: evidence erased.', [
            'mode' => $mode,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'versions' => $versions,
            'decisions' => $decisions,
            'audit_entry' => $record->getKey(),
        ]);

        return new ErasureReport($mode, $versions, $decisions, $evidenceFiles, $auditEntries, $record);
    }

    /**
     * @return list<string>
     */
    private function versionIds(string $subjectType, string $subjectId): array
    {
        /** @var list<string> $ids */
        $ids = DB::connection($this->connectionName())->table(AcceptanceConfig::versionsTable())
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->pluck('id')
            ->all();

        return $ids;
    }

    private function auditEntries(string $subjectType, string $subjectId): Builder
    {
        return DB::connection($this->connectionName())->table(AcceptanceConfig::auditTable())
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId);
    }

    /**
     * The tenant of the subject's versions, so the erasure record stays visible to that tenant.
     */
    private function tenantKey(string $subjectType, string $subjectId): ?string
    {
        $tenant = DB::connection($this->connectionName())->table(AcceptanceConfig::versionsTable())
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNotNull(AcceptanceConfig::tenantColumn())
            ->value(AcceptanceConfig::tenantColumn());

        return is_scalar($tenant) ? (string) $tenant : null;
    }

    private function connectionName(): ?string
    {
        $model = AcceptanceConfig::versionModel();

        return (new $model)->getConnectionName();
    }

    private static function reason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('An erasure needs a reason; it is kept in the audit log.');
        }

        return $reason;
    }
}
