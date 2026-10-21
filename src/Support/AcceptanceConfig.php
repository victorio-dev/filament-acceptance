<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use Victorio\Acceptance\Models\AuditEntry;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * Typed access to config/filament-acceptance.php, so the rest of the package never reads an
 * untyped config value directly.
 *
 * @internal
 */
final class AcceptanceConfig
{
    public const string KEY = 'filament-acceptance';

    /**
     * @return class-string<SealedVersion>
     */
    public static function versionModel(): string
    {
        /** @var class-string<SealedVersion> $model */
        $model = config(self::KEY.'.models.version', SealedVersion::class);

        return $model;
    }

    /**
     * @return class-string<Decision>
     */
    public static function decisionModel(): string
    {
        /** @var class-string<Decision> $model */
        $model = config(self::KEY.'.models.decision', Decision::class);

        return $model;
    }

    /**
     * @return class-string<AuditEntry>
     */
    public static function auditModel(): string
    {
        /** @var class-string<AuditEntry> $model */
        $model = config(self::KEY.'.models.audit', AuditEntry::class);

        return $model;
    }

    public static function auditTable(): string
    {
        return self::string('tables.audit', 'victorio_audit_entries');
    }

    /**
     * Null keeps every entry forever.
     */
    public static function auditPruneAfterDays(): ?int
    {
        $days = config(self::KEY.'.audit.prune_after_days', 90);

        return is_numeric($days) && (int) $days > 0 ? (int) $days : null;
    }

    public static function decisionsTable(): string
    {
        return self::string('tables.decisions', 'victorio_decisions');
    }

    public static function evidenceDisk(): string
    {
        return self::string('evidence.disk', 'local');
    }

    public static function evidencePath(): string
    {
        return trim(self::string('evidence.path', 'victorio/evidence'), '/');
    }

    public static function userAgentMaxLength(): int
    {
        return self::int('evidence.user_agent_max_length', 255);
    }

    public static function maxCommentLength(): int
    {
        return self::int('decisions.max_comment_length', 2000);
    }

    public static function maxReasonLength(): int
    {
        return self::int('decisions.max_reason_length', 2000);
    }

    public static function versionsTable(): string
    {
        return self::string('tables.versions', 'victorio_sealed_versions');
    }

    public static function tenancyEnabled(): bool
    {
        return (bool) config(self::KEY.'.tenancy.enabled', true);
    }

    public static function tenantColumn(): string
    {
        return self::string('tenancy.column', 'tenant_id');
    }

    private static function string(string $key, string $default): string
    {
        $value = config(self::KEY.'.'.$key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function int(string $key, int $default): int
    {
        $value = config(self::KEY.'.'.$key, $default);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
