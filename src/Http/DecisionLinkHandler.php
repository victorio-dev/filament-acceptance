<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Http;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Exceptions\ContentHashMismatch;
use Victorio\Acceptance\Exceptions\DecisionAlreadyMade;
use Victorio\Acceptance\Exceptions\DecisionRefused;
use Victorio\Acceptance\Exceptions\EvidenceFileUnavailable;
use Victorio\Acceptance\Exceptions\VersionSuperseded;
use Victorio\Acceptance\Facades\Decisions;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Contracts\BrandingResolver;
use Victorio\PublicLinks\Contracts\HandlesPublicLinkActions;
use Victorio\PublicLinks\Contracts\PublicLinkHandler;
use Victorio\PublicLinks\Data\PublicLinkVisit;

/**
 * The public page of a sealed version: its content with an accept and a decline form, and after
 * the decision a read-only confirmation. Register a subclass with public-links for links whose
 * `linkable` is a SealedVersion:
 *
 *     PublicLinks::handle(SealedVersion::class, 'quote', QuoteLinkHandler::class, 'Quote');
 *
 * A subclass renders its own content (renderContent(), title()) and may decide when a version can
 * be decided on (isActionable()), what the consent checkbox says (consentText()), the language
 * (locale()) and the status change that goes with a decision (transition()). The package handles
 * every state, the forms, validation and the post/redirect/get flow.
 *
 * Opening the link never spends a use; a recorded decision spends one. Issue decision links
 * without a usage limit: with `maxUses(1)` the decision exhausts the link, and the confirmation
 * page after the redirect answers 410. A second decision is impossible anyway (one per version).
 */
abstract class DecisionLinkHandler implements HandlesPublicLinkActions, PublicLinkHandler
{
    public const string NOTICE = 'filament-acceptance.notice';

    /**
     * The sealed content, rendered by the consumer. Escape everything that is not trusted markup.
     */
    abstract protected function renderContent(SealedVersion $version, PublicLinkVisit $visit): Htmlable;

    /**
     * The page heading and title, e.g. "Quote Q-2026-0042".
     */
    abstract protected function title(SealedVersion $version): string;

    /**
     * Whether the version can still be decided on, e.g. not past its validity.
     */
    protected function isActionable(SealedVersion $version): bool
    {
        return true;
    }

    /**
     * The checkbox label. Exactly this text is stored with an acceptance.
     */
    protected function consentText(SealedVersion $version): string
    {
        return self::translate('form.consent');
    }

    /**
     * The language of the page, its validation messages and the stored decision.
     */
    protected function locale(SealedVersion $version): string
    {
        return App::getLocale();
    }

    /**
     * The timezone in which the page shows the time of a decision.
     */
    protected function timezone(SealedVersion $version): string
    {
        $timezone = config('app.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    /**
     * The consumer's status change, in the same transaction as the decision; false refuses it.
     */
    protected function transition(SealedVersion $version, DecisionKind $kind): bool
    {
        return true;
    }

    public function countsOpensAsUses(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return ['accept', 'decline', 'evidence'];
    }

    public function show(PublicLinkVisit $visit): Response
    {
        $version = $this->version($visit);
        App::setLocale($this->locale($version));

        if ($version->isSuperseded()) {
            return $this->statusPage($visit, 'superseded', 410);
        }

        $decision = $version->decision()->first();
        $actionable = $decision === null && $this->isActionable($version);
        $consentText = $this->consentText($version);
        $notice = $visit->request->hasSession() ? $visit->request->session()->get(self::NOTICE) : null;

        return response()->view('filament-acceptance::page', [
            'visit' => $visit,
            'version' => $version,
            'title' => $this->title($version),
            'content' => $this->renderContent($version, $visit),
            'decision' => $decision,
            'decidedAt' => $decision === null ? null : $this->decidedAt($decision, $version),
            'actionable' => $actionable,
            'closed' => $decision === null && ! $actionable,
            'consentText' => $consentText,
            'consentHash' => hash('sha256', $consentText),
            'action' => $this->pageUrl($visit),
            'notice' => is_string($notice) ? $notice : null,
            'contactEmail' => $this->contactEmail($visit),
        ]);
    }

    public function handleAction(string $action, PublicLinkVisit $visit): Response
    {
        $version = $this->version($visit);
        App::setLocale($this->locale($version));

        $kind = match ($action) {
            'accept' => DecisionKind::Accepted,
            'decline' => DecisionKind::Declined,
            'evidence' => null,
            // A subclass that adds actions handles them before calling this method.
            default => abort(404),
        };

        if ($kind === null) {
            return $this->evidence($version);
        }

        if ($version->isSuperseded()) {
            return $this->backToPage($visit);
        }

        if (! $this->isActionable($version)) {
            return $this->backToPage($visit, 'closed');
        }

        $bag = $kind === DecisionKind::Accepted ? 'accept' : 'decline';

        try {
            $input = $this->input($version, $kind, $visit);

            Decisions::record(
                $version,
                $input,
                request: $visit->request,
                link: $visit->link,
                access: $visit->access,
                transition: fn (): bool => $this->transition($version, $kind),
            );
        } catch (ValidationException $exception) {
            return $this->backToPage($visit)->withErrors($exception->errors(), $bag)->withInput();
        } catch (DecisionAlreadyMade) {
            return $this->backToPage($visit, 'already_decided');
        } catch (VersionSuperseded) {
            return $this->backToPage($visit);
        } catch (DecisionRefused) {
            return $this->backToPage($visit, 'closed');
        } catch (ContentHashMismatch) {
            return $this->statusPage($visit, 'unverifiable', 409);
        }

        $visit->link->recordUse();

        return $this->backToPage($visit, $kind === DecisionKind::Accepted ? 'accepted' : 'declined');
    }

    /**
     * Validates the submitted form and builds the input. The consent text is the server's own,
     * and only when the page the visitor saw showed that same text (its hash came with the form).
     *
     * @throws ValidationException
     */
    private function input(SealedVersion $version, DecisionKind $kind, PublicLinkVisit $visit): DecisionInput
    {
        $request = $visit->request;
        $text = static fn (string $key): ?string => is_string($value = $request->input($key)) ? $value : null;

        if ($kind === DecisionKind::Declined) {
            $input = DecisionInput::decline(name: $text('decline_name'), email: $text('decline_email'), reason: $text('reason'), locale: App::getLocale());
            $this->validate($input, $kind, ['name' => 'decline_name', 'email' => 'decline_email', 'reason' => 'reason'], []);

            return $input;
        }

        $consentText = $this->consentText($version);
        $input = DecisionInput::accept(name: $text('name') ?? '', email: $text('email') ?? '', consentText: $consentText, comment: $text('comment'), locale: App::getLocale());

        $extra = [];

        if (! $request->boolean('consent')) {
            $extra['consent'] = [self::translate('validation.accepted', ['attribute' => self::translate('attributes.consent_text')])];
        } elseif (! hash_equals(hash('sha256', $consentText), $text('consent_hash') ?? '')) {
            $extra['consent'] = [self::translate('validation.consent_changed')];
        }

        $this->validate($input, $kind, ['name' => 'name', 'email' => 'email', 'comment' => 'comment'], $extra);

        return $input;
    }

    /**
     * @param  array<string, string>  $fields  input field => form field
     * @param  array<string, list<string>>  $extra  errors found before the rules ran
     *
     * @throws ValidationException
     */
    private function validate(DecisionInput $input, DecisionKind $kind, array $fields, array $extra): void
    {
        $rules = array_intersect_key(DecisionInput::rules($kind), $fields);
        $attributes = [];

        foreach ($fields as $field => $formField) {
            $attributes[$field] = self::translate('attributes.'.$field);
        }

        $validator = Validator::make(array_intersect_key($input->toArray(), $fields), $rules, [
            'required' => self::translate('validation.required'),
            'email' => self::translate('validation.email'),
            'max' => self::translate('validation.max'),
            'string' => self::translate('validation.required'),
        ], $attributes);

        $errors = $extra;

        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[$fields[$field] ?? $field] = array_values(array_map(strval(...), $messages));
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function evidence(SealedVersion $version): Response
    {
        $decision = $version->decision()->first();

        if (! $decision instanceof Decision || $decision->evidence_path === null) {
            abort(404);
        }

        try {
            $contents = $decision->evidenceContents();
        } catch (EvidenceFileUnavailable) {
            abort(404);
        }

        // The stored name without the hash prefix the package added.
        $filename = Str::after(basename($decision->evidence_path), '-');

        return response($contents, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, Str::ascii($filename)),
        ]);
    }

    /**
     * The time of the decision in the page's timezone and language, e.g. "October 21, 2026 2:05 PM".
     */
    private function decidedAt(Decision $decision, SealedVersion $version): string
    {
        $date = CarbonImmutable::instance($decision->decided_at)->setTimezone($this->timezone($version));
        $localized = $date->locale(App::getLocale());

        return ($localized instanceof CarbonImmutable ? $localized : $date)->isoFormat('LLL');
    }

    private function version(PublicLinkVisit $visit): SealedVersion
    {
        $version = $visit->linkable;

        if (! $version instanceof SealedVersion) {
            abort(404);
        }

        // Fresh from the database: the decision state must not come from a stale copy.
        return $version->newQuery()->whereKey($version->getKey())->firstOrFail();
    }

    /**
     * The link's own page; an action URL is the page URL plus "/{action}". The Referer header is
     * off on these pages (no-referrer), so back() would not work.
     */
    private function pageUrl(PublicLinkVisit $visit): string
    {
        $url = $visit->request->url();

        return $visit->action === null ? $url : Str::beforeLast($url, '/');
    }

    private function backToPage(PublicLinkVisit $visit, ?string $notice = null): RedirectResponse
    {
        // Through the redirector, which attaches the session and the request (withInput() reads
        // the submitted values from it).
        $redirect = redirect($this->pageUrl($visit), 303);

        return $notice === null ? $redirect : $redirect->with(self::NOTICE, $notice);
    }

    private function statusPage(PublicLinkVisit $visit, string $kind, int $status): Response
    {
        return response()->view('filament-acceptance::status', [
            'visit' => $visit,
            'kind' => $kind,
            'contactEmail' => $this->contactEmail($visit),
        ], $status);
    }

    /**
     * The contact address of the link's branding, through public-links' public resolver contract
     * and config key (a per-tenant resolver is honoured).
     */
    private function contactEmail(PublicLinkVisit $visit): ?string
    {
        $resolver = config('filament-public-links.branding.resolver');

        if (is_string($resolver) && is_a($resolver, BrandingResolver::class, true)) {
            $branding = app($resolver);

            return $branding instanceof BrandingResolver ? $branding->resolve($visit->link)->contactEmail : null;
        }

        $email = config('filament-public-links.branding.contact_email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function translate(string $key, array $replace = []): string
    {
        $text = __('filament-acceptance::filament-acceptance.'.$key, $replace);

        return is_string($text) ? $text : $key;
    }
}
