<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use LogicException;
use Victorio\Acceptance\Models\Decision;

/**
 * A decision holds at most one evidence file, written once.
 */
final class EvidenceAlreadyStored extends LogicException
{
    public static function for(Decision $decision): self
    {
        return new self(sprintf('Decision %s already has an evidence file.', $decision->getKey()));
    }
}
