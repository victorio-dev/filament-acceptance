<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use RuntimeException;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * A newer version of the document was sealed; only the latest version can be decided on.
 */
final class VersionSuperseded extends RuntimeException
{
    public static function of(SealedVersion $version): self
    {
        return new self(sprintf('Sealed version %s was superseded by a newer version and can no longer be decided on.', $version->getKey()));
    }
}
