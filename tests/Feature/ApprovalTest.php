<?php

declare(strict_types=1);

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withHeaders;

use Victorio\Acceptance\AcceptancePlugin;
use Victorio\Acceptance\Approval\ApprovalContent;
use Victorio\Acceptance\Approval\ApprovalField;
use Victorio\Acceptance\Exceptions\ApprovalNotConfigured;
use Victorio\Acceptance\Exceptions\NotCanonicalizable;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\Document;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\SoftSubject;
use Victorio\PublicLinks\Builders\PendingPublicLink;

/**
 * @param  array<string, mixed>  $attributes
 */
function sampleDocument(array $attributes = []): Document
{
    return Document::create([
        'title' => 'Website redesign',
        'body' => "First paragraph.\n\nSecond <b>paragraph</b>.",
        'amount' => 1200,
        'urgent' => true,
        'due_on' => '2026-11-30',
        ...$attributes,
    ]);
}

/**
 * A document sealed for approval in a language, and the path of its link (as the action does it).
 *
 * @return array{version: SealedVersion, path: string}
 */
function sentForApproval(Document $document, string $locale): array
{
    $version = $document->seal(ApprovalContent::KIND, ApprovalContent::for($document, $locale));
    $issued = (new PendingPublicLink($version, 'approval'))->locale($locale)->issue();

    return ['version' => $version, 'path' => '/p/'.$issued->token];
}

describe('the sealed content', function (): void {
    it('holds the fields with their labels in the chosen language only', function (): void {
        $content = ApprovalContent::for(sampleDocument(), 'hu');

        expect($content)->toBe([
            'kind' => 'approval',
            'locale' => 'hu',
            'title' => 'Website redesign',
            'fields' => [
                ['key' => 'body', 'label' => 'Szöveg', 'type' => 'multiline', 'value' => "First paragraph.\n\nSecond <b>paragraph</b>."],
                ['key' => 'amount', 'label' => 'Összeg', 'type' => 'integer', 'value' => 1200],
                ['key' => 'urgent', 'label' => 'Sürgős', 'type' => 'boolean', 'value' => true],
                ['key' => 'due_on', 'label' => 'Határidő', 'type' => 'date', 'value' => '2026-11-30'],
            ],
        ])->and(json_encode($content, JSON_UNESCAPED_UNICODE))->not->toContain('Amount');
    });

    it('falls back to the English label', function (): void {
        $labels = array_column(ApprovalContent::for(sampleDocument(), 'de')['fields'], 'label');

        expect($labels)->toBe(['Text', 'Amount', 'Urgent', 'Due on']);
    });

    it('accepts a translation key as a label', function (): void {
        app('translator')->addLines(['approval.client' => 'Kunde'], 'de', 'fixture');
        app(Victorio\Acceptance\Approval\Approvables::class)->register(IntSubject::class, 'name', [
            ApprovalField::make('name')->label('fixture::approval.client'),
        ]);

        expect(ApprovalContent::for(IntSubject::create(['name' => 'Acme']), 'de')['fields'][0]['label'])->toBe('Kunde');
    });

    it('lets the plugin win over the model', function (): void {
        AcceptancePlugin::make()->approvable(Document::class, title: fn (Document $document): string => 'Offer '.$document->title, fields: [
            ApprovalField::make('title')->label(['en' => 'Subject']),
        ]);

        $content = ApprovalContent::for(sampleDocument(), 'en');

        expect($content['title'])->toBe('Offer Website redesign')
            ->and(array_column($content['fields'], 'key'))->toBe(['title']);
    });

    it('refuses a model without approval fields', function (): void {
        expect(fn (): array => ApprovalContent::for(SoftSubject::create(['name' => 'x']), 'en'))->toThrow(ApprovalNotConfigured::class);
    });

    it('refuses a float, as all sealed content does', function (): void {
        Document::$withPrice = true;
        $document = sampleDocument(['price' => 19.99]);

        expect(fn (): SealedVersion => $document->seal(ApprovalContent::KIND, ApprovalContent::for($document, 'en')))
            ->toThrow(NotCanonicalizable::class);
    });
});

describe('the built-in decision page', function (): void {
    it('shows the fields, escaped, in the language the version was sent in', function (): void {
        ['path' => $path] = sentForApproval(sampleDocument(), 'hu');

        withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])->get($path)->assertOk()
            ->assertSee('<html lang="hu"', false)
            ->assertSee('<h1>Website redesign</h1>', false)
            ->assertSee('<dt>Szöveg</dt>', false)
            ->assertSee('<p>First paragraph.</p>', false)
            ->assertSee('Second &lt;b&gt;paragraph&lt;/b&gt;.', false)
            ->assertSee('<dt>Sürgős</dt>', false)
            ->assertSee('Igen')
            ->assertSee('2026-11-30')
            ->assertSee('Elfogadom ezt a dokumentumot és a feltételeit.')
            ->assertDontSee('Annehmen');
    });

    it('records the decision with the consent text and errors in that language', function (): void {
        ['path' => $path] = sentForApproval(sampleDocument(), 'hu');
        $consent = (string) __('filament-acceptance::filament-acceptance.form.consent', [], 'hu');

        withHeaders(['Accept-Language' => 'en'])->post($path.'/accept', acceptForm(['name' => '', 'consent_hash' => hash('sha256', $consent)]));
        withHeaders(['Accept-Language' => 'en'])->get($path)->assertSee('Kérjük, adja meg a nevét.');

        withHeaders(['Accept-Language' => 'en'])->post($path.'/accept', acceptForm(['consent_hash' => hash('sha256', $consent)]))->assertStatus(303);

        $decision = Decision::query()->sole();

        expect($decision->consent_text)->toBe($consent)
            ->and($decision->locale)->toBe('hu');
    });

    it('shows the record\'s own consent text in the version\'s language and stores exactly that', function (): void {
        Document::$consentTexts = ['en' => 'I accept the Nordwind terms.', 'hu' => 'Elfogadom a Nordwind feltételeit.'];
        ['path' => $path] = sentForApproval(sampleDocument(), 'hu');

        withHeaders(['Accept-Language' => 'en'])->get($path)->assertOk()
            ->assertSee('Elfogadom a Nordwind feltételeit.')
            ->assertSee(hash('sha256', 'Elfogadom a Nordwind feltételeit.'))
            ->assertDontSee('Elfogadom ezt a dokumentumot és a feltételeit.')
            ->assertDontSee('I accept the Nordwind terms.');

        withHeaders(['Accept-Language' => 'en'])
            ->post($path.'/accept', acceptForm(['consent_hash' => hash('sha256', 'Elfogadom a Nordwind feltételeit.')]))
            ->assertStatus(303);

        expect(Decision::query()->sole()->consent_text)->toBe('Elfogadom a Nordwind feltételeit.');
    });

    it('refuses an acceptance of the translation once the record gives its own text', function (): void {
        ['path' => $path] = sentForApproval(sampleDocument(), 'en');
        Document::$consentTexts = ['en' => 'I accept the Nordwind terms.'];

        post($path.'/accept', acceptForm())->assertStatus(303);

        expect(Decision::query()->count())->toBe(0);
        get($path)->assertSee('I accept the Nordwind terms.');
    });

    it('keeps the translation when the record gives none', function (): void {
        Document::$consentTexts = ['de' => 'Ich akzeptiere die Nordwind-Bedingungen.'];
        ['path' => $path] = sentForApproval(sampleDocument(), 'en');

        get($path)->assertOk()->assertSee('I accept this document and its terms.')->assertDontSee('Nordwind');
    });

    it('asks a soft-deleted record for its consent text too', function (): void {
        app(Victorio\Acceptance\Approval\Approvables::class)->register(SoftSubject::class, 'name', [
            ApprovalField::make('name')->label(['en' => 'Name']),
        ]);
        $subject = SoftSubject::create(['name' => 'Nordwind']);
        $version = $subject->seal(ApprovalContent::KIND, ApprovalContent::for($subject, 'en'));
        $path = '/p/'.(new PendingPublicLink($version, 'approval'))->locale('en')->issue()->token;
        $subject->delete();

        get($path)->assertOk()->assertSee('I accept the terms of Nordwind.');
    });

    it('needs a new version for another language', function (): void {
        $document = sampleDocument();
        ['version' => $hungarian] = sentForApproval($document, 'hu');
        ['version' => $english, 'path' => $path] = sentForApproval($document, 'en');

        expect($hungarian->fresh()?->isSuperseded())->toBeTrue()
            ->and($english->number)->toBe(2)
            ->and($english->content()['locale'])->toBe('en');

        get($path)->assertOk()->assertSee('<dt>Text</dt>', false);
    });
});
