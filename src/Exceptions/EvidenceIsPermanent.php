<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Evidence (a sealed version, a decision) was about to be deleted through Eloquent. The package
 * never deletes evidence; removing it is a deliberate host decision outside Eloquent (design.md a,
 * Retention).
 */
final class EvidenceIsPermanent extends LogicException
{
    /**
     * @param  class-string<Model>|Model  $model
     */
    public static function make(string|Model $model, string $operation): self
    {
        return new self(sprintf('%s is evidence and cannot be deleted (%s).', class_basename($model), $operation));
    }
}
