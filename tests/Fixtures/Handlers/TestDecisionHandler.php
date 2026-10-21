<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures\Handlers;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Http\DecisionLinkHandler;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Data\PublicLinkVisit;

/**
 * A consumer of the decision flow, the way quotes will be one. Its knobs are static so tests can
 * turn them per case; TestCase resets them.
 */
final class TestDecisionHandler extends DecisionLinkHandler
{
    public static bool $actionable = true;

    public static bool $transition = true;

    public static ?string $consent = null;

    public static ?string $locale = null;

    public static function reset(): void
    {
        self::$actionable = true;
        self::$transition = true;
        self::$consent = null;
        self::$locale = null;
    }

    protected function renderContent(SealedVersion $version, PublicLinkVisit $visit): Htmlable
    {
        $content = $version->content();
        $title = is_string($content['title'] ?? null) ? $content['title'] : '';

        return new HtmlString('<div class="fixture-content">'.e($title).'</div>');
    }

    protected function title(SealedVersion $version): string
    {
        $content = $version->content();

        return 'Document '.(is_string($content['title'] ?? null) ? $content['title'] : '');
    }

    protected function isActionable(SealedVersion $version): bool
    {
        return self::$actionable;
    }

    protected function consentText(SealedVersion $version): string
    {
        return self::$consent ?? parent::consentText($version);
    }

    protected function locale(SealedVersion $version): string
    {
        return self::$locale ?? parent::locale($version);
    }

    protected function transition(SealedVersion $version, DecisionKind $kind): bool
    {
        return self::$transition;
    }
}
