<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use Illuminate\Database\Eloquent\Model;
use Stringable;
use UnexpectedValueException;

/**
 * Morph and tenant ids are stored as strings, so int, ULID and UUID keys share one column
 * (design.md a). Every id the package writes or binds goes through here.
 *
 * A copy of the public-links helper of the same name, which is internal there; both move to
 * victorio/support before a third package needs them (docs/roadmap.md).
 *
 * @internal
 */
final class ModelKey
{
    public static function of(Model $model): string
    {
        return self::stringify($model->getKey());
    }

    /**
     * Keys arrive as `mixed` from Eloquent; only scalars and Stringables are valid ids.
     */
    public static function stringify(mixed $key): string
    {
        if (is_scalar($key) || $key instanceof Stringable) {
            return (string) $key;
        }

        throw new UnexpectedValueException('A model key must be a scalar value.');
    }
}
