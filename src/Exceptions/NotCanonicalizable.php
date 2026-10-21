<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use InvalidArgumentException;

/**
 * The data cannot be sealed: it holds a value the canonical form does not allow (a float, an
 * object, an integer beyond ±(2^53 − 1), invalid UTF-8). Money belongs in integer minor units.
 */
final class NotCanonicalizable extends InvalidArgumentException
{
    public static function at(string $path, string $problem): self
    {
        return new self(sprintf('Cannot canonicalize %s: %s.', $path === '' ? 'the root' : '"'.$path.'"', $problem));
    }
}
