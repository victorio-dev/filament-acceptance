<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Approvals are managed with the owner record's permissions, like public links: `view` to see
 * the versions, decisions, documents and activity; `update` to send for approval. A model
 * without a policy is allowed, unless the panel uses strictAuthorization().
 *
 * @internal
 */
final class Authorizer
{
    public const string VIEW = 'view';

    public const string UPDATE = 'update';

    public static function allows(string $ability, ?Model $owner): bool
    {
        if ($owner === null) {
            return false;
        }

        if (Gate::getPolicyFor($owner) === null) {
            return ! Filament::isAuthorizationStrict();
        }

        return Gate::allows($ability, $owner);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function label(string $key, array $replace = []): string
    {
        $label = __('filament-acceptance::filament-acceptance.filament.'.$key, $replace);

        return is_string($label) ? $label : $key;
    }
}
