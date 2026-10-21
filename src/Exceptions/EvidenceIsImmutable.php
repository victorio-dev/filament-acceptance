<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Evidence (a sealed version or a decision) was about to be changed through Eloquent. Seal a new
 * version instead; a decision is final.
 */
final class EvidenceIsImmutable extends LogicException
{
    /**
     * @param  class-string<Model>|Model  $model
     */
    public static function make(string|Model $model, string $operation): self
    {
        return new self(sprintf('%s is evidence and cannot be changed (%s).', class_basename($model), $operation));
    }
}
