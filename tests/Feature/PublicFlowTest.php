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
    it('shows the content and both forms', function (): void {
        ['path' => $path, 'version' => $version] = decisionLink();

        get($path)->assertOk()
            ->assertSee('Document Website redesign')
            ->assertSee('<div class="fixture-content">Website redesign</div>', false)
            ->assertSee('I accept this document and its terms.')
            ->assertSee('value="'.hash('sha256', 'I accept this document and its terms.').'"', false)
            ->assertSee('action="http://localhost'.$path.'/accept"', false)
            ->assertSee('action="http://localhost'.$path.'/decline"', false)
            ->assertSee('<label for="fa-name">', false)
            ->assertSee('<button type="submit" class="fa-button fa-button-primary">Accept</button>', false)
            ->assertSee(substr($version->content_hash, 0, 12));
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

    it('declines with a reason: decision, audit entry, event, redirect', function (): void {
        Event::fake([DecisionDeclined::class]);
        ['path' => $path, 'subject' => $subject] = decisionLink();

        submit($path, 'decline', ['reason' => 'Too expensive.'])->assertStatus(303)->assertRedirect('http://localhost'.$path);

        expect(Decision::query()->sole()->reason)->toBe('Too expensive.')
            ->and($subject->auditEntries()->first()?->event)->toBe('declined');
        Event::assertDispatched(DecisionDeclined::class);

        get($path)->assertOk()->assertSee('Declined on')->assertSee('Too expensive.');
    });

    it('answers a second submit kindly instead of failing', function (): void {
        ['path' => $path] = decisionLink();

        submit($path, 'accept', acceptForm())->assertStatus(303);
        submit($path, 'decline', ['reason' => 'Second tab'])->assertStatus(303)->assertRedirect('http://localhost'.$path);

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
            ->assertSee('aria-invalid="true" aria-describedby="fa-email-error"', false)
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

    it('keeps decline errors in the decline form', function (): void {
        config()->set('filament-acceptance.decisions.max_reason_length', 5);
        ['path' => $path] = decisionLink();

        submit($path, 'decline', ['decline_email' => 'nope', 'reason' => 'Far too long']);

        get($path)->assertSee('id="fa-decline-email-error"', false)
            ->assertSee('id="fa-reason-error"', false)
            ->assertSee('Please use at most 5 characters.')
            ->assertDontSee('id="fa-email-error"', false);
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
