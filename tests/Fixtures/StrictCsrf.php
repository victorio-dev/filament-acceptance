<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Laravel skips CSRF checks in unit tests; this one does not. ValidateCsrfToken exists in Laravel
 * 12 and 13, and in 13 it extends PreventRequestForgery, so it can stand in for either.
 */
final class StrictCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
