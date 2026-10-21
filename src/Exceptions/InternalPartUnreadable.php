<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The internal part of a sealed version cannot be decrypted: the app key changed without the old
 * one in `app.previous_keys`. The content, its hash and verify() are not affected, because the
 * internal part is not part of the hash.
 */
final class InternalPartUnreadable extends RuntimeException
{
    public static function because(Throwable $previous): self
    {
        return new self('The internal part of this sealed version cannot be decrypted with the current app key.', previous: $previous);
    }
}
