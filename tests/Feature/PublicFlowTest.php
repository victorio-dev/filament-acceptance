<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Events\DecisionAccepted;
use Victorio\Acceptance\Events\DecisionDeclined;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\Handlers\TestDecisionHandler;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\StrictCsrf;
use Victorio\PublicLinks\Builders\PendingPublicLink;

beforeEach(function (): void {
    config()->set('filament-public-links.branding.contact_email', 'quotes@acme.test');
});

/**
 * A sealed version of a fresh subject and the path of a decision link to it.
 *
 * @param  array<string, mixed>  $content
 * @return array{version: SealedVersion, path: string, token: string, subject: IntSubject}
 */
function decisionLink(array $content = ['title' => 'Website redesign'], ?int $maxUses = null): array
{
    $subject = IntSubject::create(['name' => 'Quote']);
    $version = $subject->seal('quote', $content);
    $pending = new PendingPublicLink($version, 'decide');

    if ($maxUses !== null) {
        $pending->maxUses($maxUses);
    }

    $issued = $pending->issue();

    return ['version' => $version, 'path' => '/p/'.$issued->token, 'token' => $issued->token, 'subject' => $subject];
}

/**
 * @param  array<string, string>  $form
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function submit(string $path, string $action, array $form): TestResponse
{
    return post($path.'/'.$action, $form);
}

/**
 * @param  TestResponse<Symfony\Component\HttpFoundation\Response>  $response
 */
function assertPrivacyHeaders(TestResponse $response): void
{
    $response->assertHeader('Referrer-Policy', 'no-referrer');
    expect((string) $response->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store');
}

describe('the page', function (): void {
    it('shows the content and the decision form', function (): void {
        ['path' => $path, 'version' => $version] = decisionLink();

        get($path)->assertOk()
            ->assertSee('Document Website redesign')
            ->assertSee('<div class="fixture-content">Website redesign</div>', false)
            ->assertSee('I accept this document and its terms.')
            ->assertSee('value="'.hash('sha256', 'I accept this document and its terms.').'"', false)
            ->assertSee('action="http://localhost'.$path.'/accept"', false)
            ->assertSee('formaction="http://localhost'.$path.'/decline"', false)
            ->assertSee('<label for="fa-name">', false)
            ->assertSee('<button type="submit" class="fa-button fa-button-primary">Accept</button>', false)
            ->assertSee(substr($version->content_hash, 0, 12));
    });

    it('is one form with an Accept and a Decline button, no JavaScript', function (): void {
        ['path' => $path] = decisionLink();

        $html = (string) get($path)->assertOk()->getContent();

        expect(substr_count($html, '<form '))->toBe(1)
            ->and($html)->not->toContain('<details')
            ->and($html)->not->toContain('<script')
            // Accept first, then Decline, which goes to the decline action without the browser's check.
            ->and($html)->toMatch('#<button type="submit" class="fa-button fa-button-primary">Accept</button>\s*<button type="submit" class="fa-button fa-button-secondary" formaction="http://localhost'.preg_quote($path, '#').'/decline" formnovalidate>Decline</button>#')
            ->and($html)->toContain('If you decline, you can write the reason here.')
            ->and($html)->toMatch('/<textarea id="fa-comment"[^>]*aria-describedby="fa-comment-help"/')
            ->and($html)->toContain('min-height: 44px')
            ->and($html)->toContain('.fa-buttons { flex-direction: column; }');
    });

    it('sets autocomplete for name and e-mail, and off for the comment', function (): void {
        ['path' => $path] = decisionLink();

        $html = (string) get($path)->getContent();

        expect($html)->toMatch('/<input id="fa-name"[^>]*autocomplete="name"/')
            ->and($html)->toMatch('/<input id="fa-email"[^>]*autocomplete="email"/')
            ->and($html)->toMatch('/<textarea id="fa-comment"[^>]*autocomplete="off"/');
    });

    it('puts the token into form actions only', function (): void {
        ['path' => $path, 'token' => $token] = decisionLink();

        $html = (string) get($path)->getContent();
        $withoutActions = (string) preg_replace('/action="[^"]*"/', '', $html);

        expect(substr_count($html, $token))->toBe(2)
            ->and($withoutActions)->not->toContain($token);
    });
});

describe('deciding', function (): void {
    it('accepts: decision, audit entry, event, then a redirect to the confirmation', function (): void {
        Event::fake([DecisionAccepted::class]);
        ['path' => $path, 'version' => $version, 'subject' => $subject] = decisionLink();

        submit($path, 'accept', acceptForm())->assertStatus(303)->assertRedirect('http://localhost'.$path);

        $decision = Decision::query()->sole();

        expect($decision->decision)->toBe(DecisionKind::Accepted)
            ->and($decision->name)->toBe('Anna Kovács')
            ->and($decision->sealed_version_id)->toBe($version->getKey())
            ->and($subject->auditEntries()->pluck('event')->all())->toBe(['accepted', 'sealed']);
        Event::assertDispatched(DecisionAccepted::class);

        get($path)->assertOk()
            ->assertSee('Thank you. Your acceptance has been recorded.')
            ->assertSee('Accepted by Anna Kovács on')
            ->assertSee('Looking forward to it.')
            ->assertDontSee('fa-accept-heading', false);

        // Reloading the confirmation records nothing new.
        get($path)->assertOk();

        expect(Decision::query()->count())->toBe(1);
    });

    it('asks before declining and records nothing yet', function (): void {
        ['path' => $path, 'version' => $version] = decisionLink();

        $confirm = submit($path, 'decline', ['name' => 'Anna Kovács', 'email' => 'anna@example.test', 'comment' => 'Too expensive.'])
            ->assertOk()
            ->assertSee('Are you sure you want to decline this document? This cannot be undone.')
            ->assertSee('value="Anna Kovács"', false)
            ->assertSee('>Too expensive.</textarea>', false)
            ->assertSee('action="http://localhost'.$path.'/decline-confirm"', false)
            ->assertSee('formaction="http://localhost'.$path.'/back" formnovalidate', false)
            ->assertSee(substr($version->content_hash, 0, 12))
            // Accessible: labelled fields, real buttons, the panel named by its warning.
            ->assertSee('<label for="fa-name">', false)
            ->assertSee('<label for="fa-email">', false)
            ->assertSee('<label for="fa-comment">Reason (optional)</label>', false)
            ->assertSee('aria-labelledby="fa-decline-warning"', false)
            ->assertSee('<button type="submit" class="fa-button fa-button-decline">Yes, decline</button>', false)
            ->assertSee('<html lang="en"', false);

        assertPrivacyHeaders($confirm);

        expect(Decision::query()->count())->toBe(0)
            ->and(Victorio\PublicLinks\Models\PublicLink::query()->sole()->use_count)->toBe(0);
    });

    it('declines on "Yes, decline", with the comment as the reason', function (): void {
        Event::fake([DecisionDeclined::class]);
        ['path' => $path, 'subject' => $subject] = decisionLink();

        submit($path, 'decline', ['comment' => 'Too expensive.'])->assertOk();
        submit($path, 'decline-confirm', ['name' => 'Anna Kovács', 'email' => '', 'comment' => 'Too expensive.'])
            ->assertStatus(303)->assertRedirect('http://localhost'.$path);

        $decision = Decision::query()->sole();

        expect($decision->decision)->toBe(DecisionKind::Declined)
            ->and($decision->reason)->toBe('Too expensive.')
            ->and($decision->comment)->toBeNull()
            ->and($decision->name)->toBe('Anna Kovács')
            ->and($subject->auditEntries()->first()?->event)->toBe('declined')
            ->and(Victorio\PublicLinks\Models\PublicLink::query()->sole()->use_count)->toBe(1);
        Event::assertDispatched(DecisionDeclined::class);

        get($path)->assertOk()->assertSee('Declined by Anna Kovács on')->assertSee('Too expensive.');
    });

    it('declines with empty fields and a ticked checkbox too', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'decline', ['consent' => '1', 'consent_hash' => 'whatever'])->assertOk();
        submit($path, 'decline-confirm', ['consent' => '1'])->assertStatus(303);

        $decision = Decision::query()->sole();

        expect($decision->decision)->toBe(DecisionKind::Declined)
            ->and([$decision->name, $decision->email, $decision->reason, $decision->consent_text])->toBe([null, null, null, null]);
    });

    it('goes back to the form with the values on "Back"', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'back', ['name' => 'Anna Kovács', 'email' => 'anna@example.test', 'comment' => 'Let me think.'])
            ->assertStatus(303)->assertRedirect('http://localhost'.$path);

        get($path)->assertOk()
            ->assertSee('value="Anna Kovács"', false)
            ->assertSee('value="anna@example.test"', false)
            ->assertSee('>Let me think.</textarea>', false);

        expect(Decision::query()->count())->toBe(0);
    });

    it('records one decline when the confirmation is sent twice', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'decline-confirm', ['comment' => 'No.'])->assertStatus(303);
        submit($path, 'decline-confirm', ['comment' => 'No, really.'])->assertStatus(303);

        expect(Decision::query()->count())->toBe(1)
            ->and(Decision::query()->sole()->reason)->toBe('No.');
        get($path)->assertSee('A decision on this document had already been recorded; nothing was changed.');
    });

    it('sends the confirmation step of a superseded version to the superseded page', function (): void {
        ['path' => $path, 'subject' => $subject] = decisionLink();
        $subject->seal('quote', ['title' => 'v2']);

        submit($path, 'decline', ['comment' => 'No.'])->assertStatus(303)->assertRedirect('http://localhost'.$path);
        submit($path, 'decline-confirm', ['comment' => 'No.'])->assertStatus(303);

        get($path)->assertStatus(410)->assertSee('A newer version of this document was prepared');
        expect(Decision::query()->count())->toBe(0);
    });

    it('stores the comment as the comment when accepting', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'accept', acceptForm(['comment' => 'Start in November.']))->assertStatus(303);

        $decision = Decision::query()->sole();

        expect($decision->comment)->toBe('Start in November.')
            ->and($decision->reason)->toBeNull();
    });

    it('answers a second submit kindly instead of failing', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'accept', acceptForm())->assertStatus(303);
        submit($path, 'decline-confirm', ['comment' => 'Second tab'])->assertStatus(303)->assertRedirect('http://localhost'.$path);

        get($path)->assertOk()
            ->assertSee('A decision on this document had already been recorded; nothing was changed.')
            ->assertSee('Accepted by Anna Kovács');

        expect(Decision::query()->count())->toBe(1);
    });

    it('spends one use of the link per decision, none per open', function (): void {
        ['path' => $path, 'version' => $version] = decisionLink();

        get($path);
        get($path);
        submit($path, 'accept', acceptForm());

        $link = Victorio\PublicLinks\Models\PublicLink::query()->sole();

        expect($link->use_count)->toBe(1)
            ->and($link->open_count)->toBe(2);
    });

    it('answers 410 after the decision when the link has a usage limit of one (documented: issue decision links without one)', function (): void {
        ['path' => $path] = decisionLink(maxUses: 1);

        submit($path, 'accept', acceptForm())->assertStatus(303);

        get($path)->assertStatus(410);
    });
});

describe('versions that cannot be decided', function (): void {
    it('explains a superseded version, with the contact address', function (): void {
        ['path' => $path, 'subject' => $subject] = decisionLink();
        $subject->seal('quote', ['title' => 'Website redesign, v2']);

        get($path)->assertStatus(410)
            ->assertSee('A newer version of this document was prepared')
            ->assertSee('mailto:quotes@acme.test', false)
            ->assertDontSee('fa-accept-heading', false);

        submit($path, 'accept', acceptForm())->assertStatus(303);

        expect(Decision::query()->count())->toBe(0);
    });

    it('shows a version the consumer closed without forms, and refuses to decide it', function (): void {
        TestDecisionHandler::$actionable = false;
        ['path' => $path] = decisionLink();

        get($path)->assertOk()
            ->assertSee('Website redesign')
            ->assertSee('This document can no longer be accepted or declined.')
            ->assertDontSee('fa-accept-heading', false);

        submit($path, 'accept', acceptForm())->assertStatus(303);

        expect(Decision::query()->count())->toBe(0);
    });

    it('refuses when the consumer\'s transition says no', function (): void {
        TestDecisionHandler::$transition = false;
        ['path' => $path] = decisionLink();

        submit($path, 'accept', acceptForm())->assertStatus(303);

        expect(Decision::query()->count())->toBe(0);
        get($path)->assertSee('This document can no longer be accepted or declined.');
    });

    it('answers 409 for a tampered version and records nothing', function (): void {
        ['path' => $path, 'version' => $version] = decisionLink();
        DB::table('victorio_sealed_versions')->where('id', $version->getKey())->update(['content' => '{"title":"Changed"}']);

        submit($path, 'accept', acceptForm())->assertStatus(409)
            ->assertSee('This document cannot be confirmed right now')
            ->assertSee('mailto:quotes@acme.test', false);

        expect(Decision::query()->count())->toBe(0);
    });
});

describe('validation', function (): void {
    it('keeps the values and ties the error to the field, in English', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'accept', acceptForm(['email' => 'not-an-address', 'comment' => 'Keep me']))
            ->assertStatus(303)
            ->assertRedirect('http://localhost'.$path);

        $page = get($path);
        $html = (string) $page->getContent();

        expect($html)->toMatch('/<input id="fa-email"[^>]*\bautofocus\b/')
            ->and(substr_count($html, 'autofocus'))->toBe(1);

        $page->assertOk()
            ->assertSee('Please correct the marked fields.')
            ->assertSee('Please enter a valid email address.')
            ->assertSee('value="not-an-address"', false)
            ->assertSee('value="Anna Kovács"', false)
            ->assertSee('>Keep me</textarea>', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="fa-email-error"', false)
            ->assertSee('id="fa-email-error"', false);

        expect(Decision::query()->count())->toBe(0);
    });

    it('speaks Hungarian when the page does', function (): void {
        TestDecisionHandler::$locale = 'hu';
        ['path' => $path] = decisionLink();
        $consent = (string) __('filament-acceptance::filament-acceptance.form.consent', [], 'hu');

        submit($path, 'accept', acceptForm(['name' => '', 'consent_hash' => hash('sha256', $consent)]));

        get($path)->assertOk()
            ->assertSee('Kérjük, javítsa a megjelölt mezőket.')
            ->assertSee('Kérjük, adja meg a nevét.')
            ->assertSee('Elfogadom ezt a dokumentumot és a feltételeit.')
            ->assertSee('value="anna@example.test"', false);
    });

    it('requires the consent checkbox', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'accept', acceptForm(['consent' => '']));

        get($path)->assertSee('Please tick the box to accept.')->assertSee('aria-describedby="fa-consent-error"', false);
        expect(Decision::query()->count())->toBe(0);
    });

    it('limits the reason when declining, on the comment field', function (): void {
        config()->set('filament-acceptance.decisions.max_reason_length', 5);
        ['path' => $path] = decisionLink();

        submit($path, 'decline', ['email' => 'nope', 'comment' => 'Far too long'])->assertStatus(303);

        get($path)->assertSee('id="fa-email-error"', false)
            ->assertSee('id="fa-comment-error"', false)
            ->assertSee('Please use at most 5 characters.')
            ->assertSee('aria-describedby="fa-comment-help fa-comment-error"', false);
        expect(Decision::query()->count())->toBe(0);
    });

    it('needs the consent to accept, but not to decline', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'accept', acceptForm(['consent' => '']))->assertStatus(303);
        expect(Decision::query()->count())->toBe(0);

        submit($path, 'decline-confirm', [])->assertStatus(303);
        expect(Decision::query()->sole()->decision)->toBe(DecisionKind::Declined);
    });
});

describe('consent text', function (): void {
    it('stores exactly the text the visitor saw', function (): void {
        TestDecisionHandler::$consent = 'I accept quote Q-1 and the terms of 1 October.';
        ['path' => $path] = decisionLink();

        get($path)->assertSee('I accept quote Q-1 and the terms of 1 October.');
        submit($path, 'accept', acceptForm(['consent_hash' => hash('sha256', 'I accept quote Q-1 and the terms of 1 October.')]));

        expect(Decision::query()->sole()->consent_text)->toBe('I accept quote Q-1 and the terms of 1 October.');
    });

    it('refuses when the text changed between showing and submitting', function (): void {
        ['path' => $path] = decisionLink();

        get($path);
        TestDecisionHandler::$consent = 'New terms.';
        submit($path, 'accept', acceptForm());

        expect(Decision::query()->count())->toBe(0);
        get($path)->assertSee('The terms changed while the page was open.')->assertSee('New terms.');
    });
});

it('rejects a submit without a CSRF token with 419', function (): void {
    $csrf = class_exists('Illuminate\Foundation\Http\Middleware\PreventRequestForgery')
        ? 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery'
        : ValidateCsrfToken::class;
    app()->bind($csrf, StrictCsrf::class);
    ['path' => $path] = decisionLink();

    submit($path, 'accept', acceptForm())->assertStatus(419);

    expect(Decision::query()->count())->toBe(0);

    withSession(['_token' => 'csrf-test-token'])
        ->post($path.'/accept', acceptForm(['_token' => 'csrf-test-token']))
        ->assertStatus(303);

    expect(Decision::query()->count())->toBe(1);
});

describe('escaping', function (): void {
    it('escapes the sealed content', function (): void {
        ['path' => $path] = decisionLink(['title' => '<script>alert("content")</script>']);

        get($path)->assertOk()
            ->assertDontSee('<script>alert("content")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;content&quot;)&lt;/script&gt;', false);
    });

    it('escapes what the visitor typed, in the form and on the confirmation', function (): void {
        ['path' => $path] = decisionLink();
        $script = '<script>alert("x")</script>';

        submit($path, 'accept', acceptForm(['email' => 'bad', 'name' => $script]));
        get($path)->assertDontSee($script, false)->assertSee(e($script), false);

        submit($path, 'accept', acceptForm(['name' => $script, 'comment' => $script]));
        get($path)->assertDontSee($script, false)->assertSee('Accepted by '.e($script), false);
    });
});

describe('after the decision', function (): void {
    it('is read-only and offers the evidence file', function (): void {
        Storage::fake('local');
        ['path' => $path] = decisionLink();
        submit($path, 'accept', acceptForm());
        Decision::query()->sole()->storeEvidence('%PDF-1.7 accepted', 'Q-2026-0042.pdf');

        get($path)->assertOk()
            ->assertDontSee('name="consent"', false)
            ->assertSee('Download the document')
            ->assertSee('action="http://localhost'.$path.'/evidence"', false);

        $download = submit($path, 'evidence', [])->assertOk();

        expect($download->getContent())->toBe('%PDF-1.7 accepted')
            ->and((string) $download->headers->get('Content-Disposition'))->toContain('Q-2026-0042.pdf');
    });

    it('refuses to serve a changed or missing evidence file', function (): void {
        Storage::fake('local');
        ['path' => $path] = decisionLink();
        submit($path, 'accept', acceptForm());
        $decision = Decision::query()->sole();
        $decision->storeEvidence('%PDF', 'quote.pdf');
        Storage::disk('local')->put((string) $decision->evidence_path, 'tampered');

        submit($path, 'evidence', [])->assertNotFound();
    });

    it('has no evidence to download before a decision', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'evidence', [])->assertNotFound();
    });
});

it('sends the privacy headers on every page', function (): void {
    // (the decline confirmation is checked in "asks before declining")
    Storage::fake('local');
    ['path' => $path, 'subject' => $subject] = decisionLink();
    ['path' => $tampered, 'version' => $tamperedVersion] = decisionLink();
    DB::table('victorio_sealed_versions')->where('id', $tamperedVersion->getKey())->update(['content' => '{"title":"x"}']);

    assertPrivacyHeaders(get($path));                                   // forms
    assertPrivacyHeaders(submit($path, 'accept', acceptForm(['email' => 'bad'])));   // redirect with errors
    assertPrivacyHeaders(submit($path, 'accept', acceptForm()));       // redirect after the decision
    assertPrivacyHeaders(get($path));                                   // confirmation
    Decision::query()->sole()->storeEvidence('%PDF', 'quote.pdf');
    assertPrivacyHeaders(submit($path, 'evidence', []));                // download
    assertPrivacyHeaders(submit($tampered, 'accept', acceptForm()));   // 409

    $subject->seal('quote', ['title' => 'v2']);
    assertPrivacyHeaders(get($path));                                   // superseded, 410
});

it('writes right-to-left languages right to left, with logical CSS', function (): void {
    TestDecisionHandler::$locale = 'ar';
    ['path' => $path] = decisionLink();

    get($path)->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('border-inline-start', false)
        ->assertDontSee('border-left', false);
});

it('shows the page in each shipped language', function (string $locale, string $button): void {
    TestDecisionHandler::$locale = $locale;
    ['path' => $path] = decisionLink();

    get($path)->assertOk()->assertSee($button)->assertSee('<html lang="'.str_replace('_', '-', $locale).'"', false);
})->with([
    ['en', 'Accept'], ['hu', 'Elfogadom'], ['de', 'Annehmen'], ['es', 'Acepto'], ['fr', "J'accepte"],
    ['it', 'Accetto'], ['nl', 'Ik accepteer'], ['pt_BR', 'Aceito'],
]);
