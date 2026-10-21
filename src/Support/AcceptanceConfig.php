<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

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
}
