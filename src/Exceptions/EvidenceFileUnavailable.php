<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use RuntimeException;
use Victorio\Acceptance\Models\Decision;

/**
 * The evidence file of a decision cannot be served: none was stored, it is missing from its disk,
 * or its bytes no longer match the stored SHA-256.
 */
final class EvidenceFileUnavailable extends RuntimeException
{
    public static function notStored(Decision $decision): self
    {
        return new self(sprintf('Decision %s has no evidence file.', $decision->getKey()));
    }

    public static function missing(Decision $decision): self
    {
        return new self(sprintf('The evidence file of decision %s is missing from its disk.', $decision->getKey()));
    }

    public static function tampered(Decision $decision): self
    {
        return new self(sprintf('The evidence file of decision %s no longer matches its hash.', $decision->getKey()));
    }
}
