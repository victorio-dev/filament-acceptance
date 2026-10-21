<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Victorio\Acceptance\Approval\ApprovalContent;
use Victorio\Acceptance\Facades\Decisions;
use Victorio\Acceptance\Filament\RelationManagers\DecisionsRelationManager;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\Document;
use Victorio\Acceptance\Tests\Fixtures\DocumentPolicy;
use Victorio\Acceptance\Tests\Fixtures\Filament\Pages\EditDocument;
use Victorio\Acceptance\Tests\Fixtures\Filament\Pages\ListDocuments;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\Tenant;
use Victorio\Acceptance\Tests\Fixtures\UlidSubject;
use Victorio\Acceptance\Tests\Fixtures\User;
use Victorio\Acceptance\Tests\Fixtures\UuidSubject;
use Victorio\PublicLinks\Builders\PendingPublicLink;
use Victorio\PublicLinks\Models\PublicLink;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::create(['name' => 'Admin']));
});

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

function signInAs(string $name): User
{
    $user = User::create(['name' => $name]);
    actingAs($user);

    return $user;
}

/**
 * The relation manager under test, typed as mixed: Livewire's Testable is generic in Livewire 4
 * but not in 3, and the suite runs on both.
 */
function decisionsTable(Illuminate\Database\Eloquent\Model $owner): mixed
{
    return Livewire::test(DecisionsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditDocument::class]);
}

function approvalDocument(): Document
{
    return Document::create(['title' => 'Website redesign', 'body' => 'Terms.', 'amount' => 10, 'urgent' => false]);
}

/**
 * A version of the document, sealed for approval, optionally decided.
 */
function sealedForApproval(Document $document, ?string $decision = null): SealedVersion
{
    $version = $document->seal(ApprovalContent::KIND, ApprovalContent::for($document, 'en'));

    if ($decision === 'accepted') {
        Decisions::record($version, acceptance(), visitorRequest());
    } elseif ($decision === 'declined') {
        Decisions::record($version, Victorio\Acceptance\Data\DecisionInput::decline(reason: 'Too expensive'), visitorRequest());
    }

    return $version;
}

describe('SendForApprovalAction', function (): void {
    it('seals the record, issues a link to the version and shows its URL', function (): void {
        Carbon::setTestNow('2026-10-21 12:00:00');
        $document = approvalDocument();

        $page = Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->callAction('sendForApproval', data: ['expiry' => '14', 'locale' => 'hu'])
            ->assertHasNoErrors();

        $version = $document->sealedVersions()->sole();
        $link = PublicLink::query()->sole();

        expect($version->kind)->toBe('approval')
            ->and($version->content()['locale'])->toBe('hu')
            ->and($version->content()['fields'][0]['label'])->toBe('Szöveg')
            ->and($version->sealed_by_id)->toBe((string) auth()->id())
            ->and($link->linkable?->is($version))->toBeTrue()
            ->and($link->purpose)->toBe('approval')
            ->and($link->locale)->toBe('hu')
            ->and($link->max_uses)->toBeNull()
            ->and($link->expires_at?->toDateTimeString())->toBe('2026-11-04 12:00:00')
            ->and($document->auditEntries()->pluck('event')->all())->toBe(['sent', 'sealed']);

        $page->assertMountedActionModalSee((string) $link->url());
    });

    it('asks only for the expiry and the language', function (): void {
        Livewire::test(EditDocument::class, ['record' => approvalDocument()->getKey()])
            ->mountAction('sendForApproval')
            ->assertMountedActionModalSee(['Expires in', 'Language'])
            ->assertMountedActionModalDontSee(['Purpose', 'Usage limit', 'Note']);
    });

    it('works as a table row action', function (): void {
        $document = approvalDocument();

        Livewire::test(ListDocuments::class)->callTableAction('sendForApproval', $document, data: ['expiry' => '7', 'locale' => 'en'])->assertHasNoErrors();

        expect($document->sealedVersions()->count())->toBe(1);
    });

    it('never shows another record\'s link, even to a crafted request', function (): void {
        $document = approvalDocument();
        $other = sealedForApproval(approvalDocument());
        $foreign = (new PendingPublicLink($other, 'approval'))->issue();

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->call('mountAction', 'sendForApproval', ['link' => $foreign->link->getKey()], ['filamentPublicLinksUrl' => true])
            ->assertMountedActionModalDontSee($foreign->url);
    });

    it('needs update on the record', function (): void {
        Gate::policy(Document::class, DocumentPolicy::class);
        $document = approvalDocument();
        signInAs('viewer');

        decisionsTable($document)->assertTableActionHidden('sendForApproval');
        Livewire::test(ListDocuments::class)->assertTableActionHidden('sendForApproval', $document);
    });
});

describe('DecisionsRelationManager', function (): void {
    it('lists versions with their status, decision and fingerprint', function (): void {
        $document = approvalDocument();
        $superseded = sealedForApproval($document);
        $accepted = sealedForApproval($document, 'accepted');
        $other = approvalDocument();
        $declined = sealedForApproval($other, 'declined');
        $pending = sealedForApproval(approvalDocument());

        decisionsTable($document)
            ->assertCanSeeTableRecords([$accepted, $superseded])
            ->assertCanNotSeeTableRecords([$declined, $pending])
            ->assertSee(['Accepted', 'Superseded', 'Anna Kovács', 'Looking forward to it.', substr($accepted->content_hash, 0, 12), 'v2']);

        decisionsTable($other)->assertSee(['Declined', 'Too expensive']);
        decisionsTable(Document::query()->findOrFail($pending->subject_id))->assertSee('Awaiting decision');
    });

    it('works for owners with int, ULID and UUID keys', function (string $model): void {
        $owner = $model::create(['name' => 'Quote']);
        $version = $owner->seal(ApprovalContent::KIND, ApprovalContent::for($owner, 'en'));

        decisionsTable($owner)->assertCanSeeTableRecords([$version]);
    })->with([IntSubject::class, UlidSubject::class, UuidSubject::class]);

    it('downloads the stored document after checking its hash', function (): void {
        Storage::fake('local');
        $version = sealedForApproval(approvalDocument(), 'accepted');
        Decision::query()->sole()->storeEvidence('%PDF accepted', 'offer.pdf');

        decisionsTable(Document::query()->sole())
            ->callTableAction('downloadEvidence', $version)
            ->assertFileDownloaded('offer.pdf', '%PDF accepted');
    });

    it('refuses to download a changed document', function (): void {
        Storage::fake('local');
        $version = sealedForApproval(approvalDocument(), 'accepted');
        $decision = Decision::query()->sole();
        $decision->storeEvidence('%PDF', 'offer.pdf');
        Storage::disk('local')->put((string) $decision->evidence_path, 'changed');

        decisionsTable(Document::query()->sole())
            ->callTableAction('downloadEvidence', $version)
            ->assertNotified('The document could not be verified, so it was not downloaded.');
    });

    it('hides the download without a stored document', function (): void {
        $version = sealedForApproval(approvalDocument(), 'accepted');

        decisionsTable(Document::query()->sole())->assertTableActionHidden('downloadEvidence', $version);
    });

    it('verifies a version and its decision', function (): void {
        $version = sealedForApproval(approvalDocument(), 'accepted');
        $table = decisionsTable(Document::query()->sole());

        $table->callTableAction('verifySealedVersion', $version)
            ->assertNotified('Intact: the content, the decision and the document match their hashes.');

        DB::table('victorio_sealed_versions')->where('id', $version->getKey())->update(['content' => '{"kind":"approval"}']);

        decisionsTable(Document::query()->sole())->callTableAction('verifySealedVersion', $version)
            ->assertNotified('Verification failed: the content, the decision or the document was changed.');
    });

    it('shows the activity', function (): void {
        sealedForApproval(approvalDocument(), 'accepted');

        decisionsTable(Document::query()->sole())
            ->mountTableAction('viewActivity')
            ->assertMountedActionModalSee(['Activity', 'Sealed', 'Accepted']);
    });

    it('shows only the current tenant\'s versions', function (): void {
        $acme = Tenant::create(['name' => 'Acme']);
        $globex = Tenant::create(['name' => 'Globex']);
        $document = approvalDocument();
        $acmeVersion = $document->seal(ApprovalContent::KIND, ApprovalContent::for($document, 'en'), tenant: $acme);
        $globexVersion = $document->seal(ApprovalContent::KIND, ApprovalContent::for($document, 'en'), tenant: $globex);

        Filament::setTenant($acme, isQuiet: true);

        decisionsTable($document)->assertCanSeeTableRecords([$acmeVersion])->assertCanNotSeeTableRecords([$globexVersion]);
    });

    it('follows the owner record\'s policy', function (): void {
        Gate::policy(Document::class, DocumentPolicy::class);
        $document = approvalDocument();
        $version = sealedForApproval($document, 'accepted');

        signInAs('viewer');
        expect(DecisionsRelationManager::canViewForRecord($document, EditDocument::class))->toBeTrue();
        decisionsTable($document)->assertTableActionVisible('verifySealedVersion', $version)->assertTableActionVisible('viewActivity');

        signInAs('stranger');
        expect(DecisionsRelationManager::canViewForRecord($document, EditDocument::class))->toBeFalse();
    });

    it('follows strict authorization when the owner model has no policy', function (): void {
        $document = approvalDocument();

        expect(DecisionsRelationManager::canViewForRecord($document, EditDocument::class))->toBeTrue();

        Filament::getPanel('admin')->strictAuthorization();

        expect(DecisionsRelationManager::canViewForRecord($document, EditDocument::class))->toBeFalse();
    });

    it('refreshes after a link is sent from elsewhere on the page', function (): void {
        $document = approvalDocument();
        $table = decisionsTable($document);
        sealedForApproval($document);

        $table->dispatch(Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager::LINKS_CHANGED)
            ->assertCanSeeTableRecords($document->sealedVersions()->get());
    });
});
