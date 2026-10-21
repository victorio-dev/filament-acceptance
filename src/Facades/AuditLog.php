<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Facades;

use Illuminate\Support\Facades\Facade;
use Victorio\Acceptance\Support\AuditLogger;

/**
 * Adds a line to a document's timeline:
 *
 *     AuditLog::record($quote, 'sent', causer: auth()->user(), version: $version);
 *
 * Properties must not hold personal data, IPs or secrets; pass the request for the IP HMAC.
 *
 * @method static \Victorio\Acceptance\Models\AuditEntry record(\Illuminate\Database\Eloquent\Model $subject, string $event, ?\Illuminate\Database\Eloquent\Model $causer = null, ?\Victorio\Acceptance\Models\SealedVersion $version = null, array<string, mixed> $properties = [], ?\Illuminate\Http\Request $request = null, ?\Illuminate\Database\Eloquent\Model $tenant = null)
 *
 * @see AuditLogger
 */
final class AuditLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditLogger::class;
    }
}
