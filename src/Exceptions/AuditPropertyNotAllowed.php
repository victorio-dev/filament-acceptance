<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use InvalidArgumentException;

/**
 * An audit entry was given a property that looks like personal data or a secret (rule E5). The
 * check is by key name; it is a safety net, not a content scanner.
 */
final class AuditPropertyNotAllowed extends InvalidArgumentException
{
    public static function key(string $path): self
    {
        return new self(sprintf('The audit property "%s" is not allowed: no personal data, IPs or secrets in the audit log.', $path));
    }
}
