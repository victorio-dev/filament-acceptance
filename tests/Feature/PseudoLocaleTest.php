<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Tests\Fixtures\Handlers\TestDecisionHandler;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\PublicLinks\Builders\PendingPublicLink;
use Victorio\Support\Testing\PseudoLocale;

/**
 * Every visible text of the decision pages comes from a translation key: with the pseudo locale
 * on, nothing but data may appear outside ⟦ ⟧ (docs/conventions.md).
 */
beforeEach(function (): void {
    config()->set('filament-public-links.branding.contact_email', 'quotes@acme.test');
    PseudoLocale::enable();
});

/**
 * @return array{path: string, version: Victorio\Acceptance\Models\SealedVersion, subject: IntSubject}
 */
function pseudoLink(): array
{
    $subject = IntSubject::create(['name' => 'Quote']);
    $version = $subject->seal('quote', ['title' => 'Website redesign']);

    return ['path' => '/p/'.(new PendingPublicLink($version, 'decide'))->issue()->token, 'version' => $version, 'subject' => $subject];
}

/**
 * Data on the pages: the fixture's content and title, the brand, the contact address, the
 * fingerprint, what the visitor typed, the formatted decision time.
 *
 * @return list<string>
 */
function decisionData(Victorio\Acceptance\Models\SealedVersion $version, ?Decision $decision = null): array
{
    return array_values(array_filter([
        'Website redesign', 'Document', (string) config('app.name'), 'quotes@acme.test',
        substr($version->content_hash, 0, 12), 'Anna Kovács', 'anna@example.test', 'Looking forward to it.',
        'not-an-address', 'quote.pdf',
        $decision === null ? null : Carbon\CarbonImmutable::instance($decision->decided_at)->isoFormat('LLL'),
    ]));
}

it('has no hard-coded text on the decision page', function (): void {
    ['path' => $path, 'version' => $version] = pseudoLink();

    $html = (string) get($path)->assertOk()->getContent();

    expect(PseudoLocale::untranslated($html, decisionData($version)))->toBe([])
        ->and($html)->toContain('⟦');
});

it('has no hard-coded text with validation errors', function (): void {
    ['path' => $path, 'version' => $version] = pseudoLink();
    $consent = (string) __('filament-acceptance::filament-acceptance.form.consent');

    post($path.'/accept', acceptForm(['email' => 'not-an-address', 'consent_hash' => hash('sha256', $consent)]));
    post($path.'/decline', ['decline_email' => 'not-an-address']);

    expect(PseudoLocale::untranslated((string) get($path)->getContent(), decisionData($version)))->toBe([]);
});

it('has no hard-coded text on the confirmation', function (): void {
    Storage::fake('local');
    ['path' => $path, 'version' => $version] = pseudoLink();
    $consent = (string) __('filament-acceptance::filament-acceptance.form.consent');

    post($path.'/accept', acceptForm(['consent_hash' => hash('sha256', $consent)]))->assertStatus(303);
    $decision = Decision::query()->sole();
    $decision->storeEvidence('%PDF', 'quote.pdf');

    expect(PseudoLocale::untranslated((string) get($path)->getContent(), decisionData($version, $decision)))->toBe([]);
});

it('has no hard-coded text on a closed version', function (): void {
    TestDecisionHandler::$actionable = false;
    ['path' => $path, 'version' => $version] = pseudoLink();

    expect(PseudoLocale::untranslated((string) get($path)->getContent(), decisionData($version)))->toBe([]);
});

it('has no hard-coded text on the superseded and the unverifiable pages', function (): void {
    ['path' => $path, 'version' => $version, 'subject' => $subject] = pseudoLink();
    ['path' => $tampered, 'version' => $tamperedVersion] = pseudoLink();
    DB::table('victorio_sealed_versions')->where('id', $tamperedVersion->getKey())->update(['content' => '{"title":"x"}']);
    $subject->seal('quote', ['title' => 'v2']);

    $superseded = (string) get($path)->assertStatus(410)->getContent();
    $unverifiable = (string) post($tampered.'/accept', acceptForm())->assertStatus(409)->getContent();

    expect(PseudoLocale::untranslated($superseded, decisionData($version)))->toBe([])
        ->and(PseudoLocale::untranslated($unverifiable, decisionData($version)))->toBe([]);
});

describe('in Filament', function (): void {
    beforeEach(function (): void {
        Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
        Pest\Laravel\actingAs(Victorio\Acceptance\Tests\Fixtures\User::create(['name' => 'Admin']));
    });

    /**
     * Data in the admin pages: the record, the decision, fingerprints, dates (Carbon, admin
     * locale), locale names (intl).
     *
     * @param  list<Victorio\Acceptance\Models\SealedVersion>  $versions
     * @return list<string>
     */
    function approvalAdminData(array $versions): array
    {
        $dates = [];

        foreach (Victorio\Acceptance\Models\SealedVersion::query()->get() as $version) {
            $dates[] = Carbon\CarbonImmutable::instance($version->sealed_at)->isoFormat('LLL');
        }

        foreach (Decision::query()->get() as $decision) {
            $dates[] = Carbon\CarbonImmutable::instance($decision->decided_at)->isoFormat('LLL');
        }

        foreach (Victorio\Acceptance\Models\AuditEntry::query()->get() as $entry) {
            $dates[] = Carbon\CarbonImmutable::instance($entry->occurred_at)->isoFormat('LLL');
        }

        return [
            (string) config('app.name'), 'Website redesign', 'Anna Kovács', 'Looking forward to it.', 'Admin',
            ...array_map(static fn ($version): string => substr($version->content_hash, 0, 12), $versions),
            ...$dates,
            'Deutsch', 'English', 'Español', 'Français', 'Italiano', 'Magyar', 'Nederlands', 'Português (Brasil)',
        ];
    }

    it('has no hard-coded text in the decisions table, the send modal and the activity', function (): void {
        $document = Victorio\Acceptance\Tests\Fixtures\Document::create(['title' => 'Website redesign', 'body' => 'Terms.']);
        $first = $document->seal(Victorio\Acceptance\Approval\ApprovalContent::KIND, Victorio\Acceptance\Approval\ApprovalContent::for($document, 'en'));
        $second = $document->seal(Victorio\Acceptance\Approval\ApprovalContent::KIND, Victorio\Acceptance\Approval\ApprovalContent::for($document, 'en'));
        Victorio\Acceptance\Facades\Decisions::record($second, acceptance(), visitorRequest());

        $component = Livewire\Livewire::test(Victorio\Acceptance\Filament\RelationManagers\DecisionsRelationManager::class, [
            'ownerRecord' => $document,
            'pageClass' => Victorio\Acceptance\Tests\Fixtures\Filament\Pages\EditDocument::class,
        ]);
        $allowed = approvalAdminData([$first, $second]);

        expect(PseudoLocale::untranslated($component->html(), $allowed))->toBe([])
            ->and($component->html())->toContain('⟦')
            ->and(PseudoLocale::untranslated($component->mountTableAction('sendForApproval')->html(), $allowed))->toBe([]);

        $activity = Livewire\Livewire::test(Victorio\Acceptance\Filament\RelationManagers\DecisionsRelationManager::class, [
            'ownerRecord' => $document,
            'pageClass' => Victorio\Acceptance\Tests\Fixtures\Filament\Pages\EditDocument::class,
        ])->mountTableAction('viewActivity');

        expect(PseudoLocale::untranslated($activity->html(), $allowed))->toBe([]);
    });
});
