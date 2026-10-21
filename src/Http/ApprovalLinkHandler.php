<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Http;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\HtmlString;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Data\PublicLinkVisit;

/**
 * The built-in decision page for records sent with SendForApprovalAction: it renders the sealed
 * fields (ApprovalContent) and shows the page in the language the version was sent in, whatever
 * the browser asks for. Registered for SealedVersion under `approval.purpose`.
 */
final class ApprovalLinkHandler extends DecisionLinkHandler
{
    protected function renderContent(SealedVersion $version, PublicLinkVisit $visit): Htmlable
    {
        $content = $version->content();
        $fields = is_array($content['fields'] ?? null) ? array_values(array_filter($content['fields'], is_array(...))) : [];

        /** @var view-string $view */
        $view = 'filament-acceptance::approval.content';

        return new HtmlString(view($view, ['fields' => $fields])->render());
    }

    protected function title(SealedVersion $version): string
    {
        $title = $version->content()['title'] ?? null;

        return is_string($title) ? $title : '';
    }

    /**
     * The record's approvalConsentText() when it gives one, in the version's language (the page
     * sets it before asking); otherwise the translation. Asked of the record as it is now, also
     * when soft-deleted.
     */
    protected function consentText(SealedVersion $version): string
    {
        $subject = self::subjectOf($version);
        $text = $subject !== null && method_exists($subject, 'approvalConsentText') ? $subject->approvalConsentText() : null;

        return is_string($text) && trim($text) !== '' ? $text : parent::consentText($version);
    }

    protected function locale(SealedVersion $version): string
    {
        $locale = $version->content()['locale'] ?? null;

        return is_string($locale) && $locale !== '' ? $locale : App::getLocale();
    }

    /**
     * The sealed record, without global scopes: the link is the authorization here, and a
     * soft-deleted or other-tenant record keeps its text.
     */
    private static function subjectOf(SealedVersion $version): ?Model
    {
        $class = Relation::getMorphedModel($version->subject_type) ?? $version->subject_type;

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        return (new $class)->newQueryWithoutScopes()->whereKey($version->subject_id)->first();
    }
}
