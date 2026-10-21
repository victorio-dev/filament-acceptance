<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures\Handlers;

use Symfony\Component\HttpFoundation\Response;
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Facades\Decisions;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Contracts\HandlesPublicLinkActions;
use Victorio\PublicLinks\Contracts\PublicLinkHandler;
use Victorio\PublicLinks\Data\PublicLinkVisit;

/**
 * A minimal public-links handler that records an acceptance, the way the decision flow of step 4
 * will: with the visit's request, link and access row.
 */
final class DecideVersion implements HandlesPublicLinkActions, PublicLinkHandler
{
    public static ?Decision $lastDecision = null;

    public function show(PublicLinkVisit $visit): Response
    {
        return response('version');
    }

    public function countsOpensAsUses(): bool
    {
        return false;
    }

    public function actions(): array
    {
        return ['accept'];
    }

    public function handleAction(string $action, PublicLinkVisit $visit): Response
    {
        /** @var SealedVersion $version */
        $version = $visit->linkable;

        self::$lastDecision = Decisions::record(
            $version,
            DecisionInput::accept(name: 'Anna Kovács', email: 'anna@example.test', consentText: 'I accept.'),
            request: $visit->request,
            link: $visit->link,
            access: $visit->access,
        );

        return response('accepted');
    }
}
