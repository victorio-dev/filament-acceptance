<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Facades;

use Illuminate\Support\Facades\Facade;
use Victorio\Acceptance\Support\DecisionRecorder;

/**
 * Records the one decision of a sealed version:
 *
 *     Decisions::record($version, DecisionInput::accept(...), request: $request, transition: fn (): bool => …);
 *
 * @method static \Victorio\Acceptance\Models\Decision record(\Victorio\Acceptance\Models\SealedVersion $version, \Victorio\Acceptance\Data\DecisionInput $input, ?\Illuminate\Http\Request $request = null, ?\Victorio\PublicLinks\Models\PublicLink $link = null, ?\Victorio\PublicLinks\Models\PublicLinkAccess $access = null, (\Closure(): bool)|null $transition = null)
 *
 * @see DecisionRecorder
 */
final class Decisions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DecisionRecorder::class;
    }
}
