<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use RuntimeException;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * The stored content of a sealed version no longer matches its hash: it was changed below
 * Eloquent. No decision is recorded on content that cannot be proven.
 */
final class ContentHashMismatch extends RuntimeException
{
    public static function of(SealedVersion $version): self
    {
        return new self(sprintf('Sealed version %s failed verification; no decision was recorded.', $version->getKey()));
    }
}
