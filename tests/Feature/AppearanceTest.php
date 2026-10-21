<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Victorio\Acceptance\AcceptancePlugin;
use Victorio\Acceptance\Approval\ApprovalContent;
use Victorio\Acceptance\Filament\Actions\DownloadEvidenceAction;
use Victorio\Acceptance\Filament\Actions\SendForApprovalAction;
use Victorio\Acceptance\Filament\Actions\VerifySealedVersionAction;
use Victorio\Acceptance\Filament\Actions\ViewActivityAction;
use Victorio\Acceptance\Filament\RelationManagers\DecisionsRelationManager;
use Victorio\Acceptance\Filament\Support\OwnerRecord;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\Document;
use Victorio\Acceptance\Tests\Fixtures\DocumentPolicy;
use Victorio\Acceptance\Tests\Fixtures\Filament\Pages\EditDocument;
use Victorio\Acceptance\Tests\Fixtures\User;
use Victorio\Support\Testing\PseudoLocale;

/**
 * The plugin's appearance options for the decisions table: the row-action presets and the
 * modify*ActionsUsing() closures (docs/conventions.md, "Filament appearance").
 */
beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::create(['name' => 'Admin']));
});

function withAcceptancePlugin(AcceptancePlugin $plugin): void
{
    Filament::getPanel('admin')->plugin($plugin);
}

/**
 * A document with one sealed version.
 *
 * @return array{Document, SealedVersion}
 */
function sealedDocument(): array
{
    $document = Document::create(['title' => 'Offer', 'body' => 'Terms.', 'urgent' => false]);

    return [$document, $document->seal(ApprovalContent::KIND, ApprovalContent::for($document, 'en'))];
}

/**
 * The decisions table, typed as mixed: Livewire's Testable is generic in Livewire 4 but not in 3.
 */
function versionsTable(Document $document): mixed
{
    return Livewire::test(DecisionsRelationManager::class, ['ownerRecord' => $document, 'pageClass' => EditDocument::class]);
}

/**
 * @return list<Action|ActionGroup>
 */
function versionRowActions(mixed $component): array
{
    return array_values($component->instance()->getTable()->getRecordActions());
}

/**
 * @return array<Action|ActionGroup>
 */
function actionsInGroup(Action|ActionGroup $group): array
{
    expect($group)->toBeInstanceOf(ActionGroup::class);

    return $group instanceof ActionGroup ? $group->getActions() : [];
}

/**
 * @param  array<Action|ActionGroup>  $actions
 * @return list<string|null>
 */
function namesOf(array $actions): array
{
    return array_values(array_map(static fn (Action|ActionGroup $action): ?string => $action instanceof Action ? $action->getName() : null, $actions));
}

describe('row action presets', function (): void {
    it('shows icon buttons with the label as tooltip by default, as the table is wide', function (): void {
        [$document] = sealedDocument();
        $actions = versionRowActions(versionsTable($document));

        expect(namesOf($actions))->toBe(['downloadEvidence', 'verifySealedVersion'])
            ->and(array_map(static fn (Action|ActionGroup $action): bool => $action->isIconButton(), $actions))->toBe([true, true])
            ->and(array_map(static fn (Action|ActionGroup $action): mixed => $action instanceof Action ? $action->getTooltip() : null, $actions))
            ->toBe(['Download document', 'Verify']);
    });

    it('shows labelled actions', function (): void {
        withAcceptancePlugin(AcceptancePlugin::make()->rowActions('labels'));
        [$document] = sealedDocument();

        expect(array_map(static fn (Action|ActionGroup $action): bool => $action->isIconButton(), versionRowActions(versionsTable($document))))
            ->toBe([false, false]);
    });

    it('collects them in one group', function (): void {
        withAcceptancePlugin(AcceptancePlugin::make()->rowActions('group'));
        [$document] = sealedDocument();
        $actions = versionRowActions(versionsTable($document));

        expect($actions)->toHaveCount(1)
            ->and(namesOf(actionsInGroup($actions[0])))->toBe(['downloadEvidence', 'verifySealedVersion']);
    });

    it('refuses an unknown preset', function (): void {
        expect(fn (): AcceptancePlugin => AcceptancePlugin::make()->rowActions('menu'))
            ->toThrow(InvalidArgumentException::class, 'labels", "icons", "group');
    });
});

describe('modify*ActionsUsing()', function (): void {
    it('reorders, omits and changes row actions, before the group wraps them', function (): void {
        withAcceptancePlugin(AcceptancePlugin::make()->rowActions('group')->modifyRowActionsUsing(
            fn (array $actions): array => [$actions['verifySealedVersion']->label('Check'), Action::make('archive')],
        ));
        [$document, $version] = sealedDocument();

        $table = versionsTable($document);

        expect(namesOf(actionsInGroup(versionRowActions($table)[0])))->toBe(['verifySealedVersion', 'archive']);
        $table->assertTableActionHasLabel('verifySealedVersion', 'Check', $version)
            ->assertTableActionDoesNotExist('downloadEvidence');
    });

    it('changes the header actions', function (): void {
        withAcceptancePlugin(AcceptancePlugin::make()->modifyHeaderActionsUsing(
            fn (array $actions): array => [$actions['viewActivity'], $actions['sendForApproval']->label('Request signature')],
        ));
        [$document] = sealedDocument();

        $headers = array_values(versionsTable($document)->instance()->getTable()->getHeaderActions());

        expect(namesOf($headers))->toBe(['viewActivity', 'sendForApproval']);
        versionsTable($document)->assertTableActionHasLabel('sendForApproval', 'Request signature');
    });

    it('adds bulk actions to a table that has none', function (): void {
        [$document] = sealedDocument();

        expect(versionsTable($document)->instance()->getTable()->getToolbarActions())->toBe([]);

        withAcceptancePlugin(AcceptancePlugin::make()->modifyToolbarActionsUsing(
            fn (array $actions): array => [...$actions, BulkAction::make('exportVersions')],
        ));
        $toolbar = array_values(versionsTable($document)->instance()->getTable()->getToolbarActions());

        expect($toolbar)->toHaveCount(1)
            ->and($toolbar[0])->toBeInstanceOf(BulkActionGroup::class)
            ->and(namesOf(actionsInGroup($toolbar[0])))->toBe(['exportVersions']);
    });
});

it('lets configureUsing() change every action of the package', function (): void {
    [$document, $version] = sealedDocument();
    $rename = fn (string $label): Closure => fn (Action $action): Action => $action->label($label);

    DownloadEvidenceAction::configureUsing($rename('D'), during: fn (): mixed => VerifySealedVersionAction::configureUsing($rename('V'), during: fn (): mixed => ViewActivityAction::configureUsing($rename('A'), during: fn (): mixed => SendForApprovalAction::configureUsing($rename('S'), during: fn (): mixed => versionsTable($document)
        ->assertTableActionHasLabel('verifySealedVersion', 'V', $version)
        ->assertTableActionHasLabel('viewActivity', 'A')
        ->assertTableActionHasLabel('sendForApproval', 'S')))));

    expect(DownloadEvidenceAction::configureUsing($rename('D'), during: fn (): mixed => DownloadEvidenceAction::make()->getLabel()))->toBe('D');
});

it('works on its own, outside the decisions table', function (): void {
    [$document, $version] = sealedDocument();

    Livewire::test(EditDocument::class, ['record' => $document->getKey()])
        ->mountAction('viewActivity')
        ->assertMountedActionModalSee('Sealed');

    // A version's own record decides when there is no relation manager.
    expect(OwnerRecord::resolve(null, $version)?->is($document))->toBeTrue();
});

dataset('acceptance presets', ['labels', 'icons', 'group']);

it('keeps the authorization in every preset', function (string $preset): void {
    withAcceptancePlugin(AcceptancePlugin::make()->rowActions($preset));
    Gate::policy(Document::class, DocumentPolicy::class);
    [$document, $version] = sealedDocument();
    actingAs(User::create(['name' => 'viewer']));

    // A viewer may look and verify; there is no stored document to download, and no sending.
    versionsTable($document)
        ->assertTableActionVisible('verifySealedVersion', $version)
        ->assertTableActionHidden('downloadEvidence', $version)
        ->assertTableActionHidden('sendForApproval');

    // A hand-crafted request for a hidden action is refused as well: no download, no notification.
    versionsTable($document)
        ->call('mountAction', 'downloadEvidence', [], ['table' => true, 'recordKey' => $version->getKey()])
        ->call('callMountedAction')
        ->assertNoFileDownloaded()
        ->assertNotNotified();
})->with('acceptance presets');

it('has no hard-coded text in the compact presets', function (string $preset): void {
    Carbon\Carbon::setTestNow('2026-10-21 12:05:00');
    withAcceptancePlugin(AcceptancePlugin::make()->rowActions($preset));
    [$document, $version] = sealedDocument();
    PseudoLocale::enable();

    $html = versionsTable($document)->html();
    // Data: the record, the version number, the sealing time (Carbon's own format), the fingerprint.
    $data = ['Offer', 'v1', Carbon\CarbonImmutable::now()->isoFormat('LLL'), substr($version->content_hash, 0, 12)];

    expect(PseudoLocale::untranslated($html, $data))->toBe([])
        ->and($html)->toContain('⟦');
})->with(['icons', 'group']);
