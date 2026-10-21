<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\RelationManagers;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Victorio\Acceptance\Exceptions\EvidenceFileUnavailable;
use Victorio\Acceptance\Filament\Actions\SendForApprovalAction;
use Victorio\Acceptance\Filament\Support\Authorizer;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;

/**
 * The sealed versions of a record and their decisions, for any resource whose model uses
 * HasSealedVersions:
 *
 *     public static function getRelations(): array
 *     {
 *         return [DecisionsRelationManager::class];
 *     }
 *
 * Status, who decided when, comment or reason, fingerprint; download of the stored document,
 * verification, the activity log, and "Send for approval". Seeing it needs `view` on the owner
 * record, sending needs `update`. With Filament tenancy only the current tenant's versions show.
 */
final class DecisionsRelationManager extends RelationManager
{
    // A StringKeyedMorphMany; Filament queries it via getQuery(), like public-links' relation manager.
    protected static string $relationship = 'sealedVersions';

    /**
     * Re-renders the table after SendForApprovalAction issued a link anywhere on the page.
     */
    #[On(PublicLinksRelationManager::LINKS_CHANGED)]
    public function refreshDecisions(): void {}

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return Authorizer::label('relation_manager.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Authorizer::allows(Authorizer::VIEW, $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('decision'))
            ->columns([
                TextColumn::make('number')
                    ->label(Authorizer::label('columns.version'))
                    ->formatStateUsing(static fn (int $state): string => Authorizer::label('columns.version_number', ['number' => (string) $state])),
                TextColumn::make('sealed_at')
                    ->label(Authorizer::label('columns.sealed_at'))
                    ->formatStateUsing(static fn (SealedVersion $record): string => self::date($record->sealed_at)),
                TextColumn::make('status')
                    ->label(Authorizer::label('columns.status'))
                    ->state(static fn (SealedVersion $record): string => self::status($record))
                    ->formatStateUsing(static fn (string $state): string => Authorizer::label('status.'.$state))
                    ->badge()
                    ->color(static fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'declined' => 'danger',
                        'superseded' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('decision.name')
                    ->label(Authorizer::label('columns.name'))
                    ->placeholder('—'),
                TextColumn::make('decided_at')
                    ->label(Authorizer::label('columns.decided_at'))
                    ->state(static fn (SealedVersion $record): ?string => $record->decision === null ? null : self::date($record->decision->decided_at))
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label(Authorizer::label('columns.note'))
                    ->state(static fn (SealedVersion $record): ?string => $record->decision === null ? null : ($record->decision->comment ?? $record->decision->reason))
                    ->limit(60)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('content_hash')
                    ->label(Authorizer::label('columns.fingerprint'))
                    ->formatStateUsing(static fn (string $state): string => substr($state, 0, 12))
                    ->fontFamily(FontFamily::Mono),
            ])
            ->emptyStateHeading(Authorizer::label('relation_manager.empty'))
            ->headerActions([
                SendForApprovalAction::make(),
                self::activityAction(),
            ])
            ->recordActions([
                self::downloadAction(),
                self::verifyAction(),
            ]);
    }

    private static function status(SealedVersion $version): string
    {
        $decision = $version->decision;

        return match (true) {
            $decision instanceof Decision => $decision->isAccepted() ? 'accepted' : 'declined',
            $version->isSuperseded() => 'superseded',
            default => 'pending',
        };
    }

    private static function date(DateTimeInterface $date): string
    {
        $date = CarbonImmutable::instance($date)->setTimezone(FilamentTimezone::get());
        $localized = $date->locale(App::getLocale());

        return ($localized instanceof CarbonImmutable ? $localized : $date)->isoFormat('LLL');
    }

    private static function owner(mixed $livewire): ?Model
    {
        $owner = $livewire instanceof RelationManager ? $livewire->getOwnerRecord() : null;

        return $owner instanceof Model ? $owner : null;
    }

    private static function downloadAction(): Action
    {
        $allowed = static fn (mixed $livewire, SealedVersion $record): bool => $record->decision?->evidence_path !== null
            && Authorizer::allows(Authorizer::VIEW, self::owner($livewire));

        return Action::make('downloadEvidence')
            ->label(Authorizer::label('actions.download.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->visible($allowed)
            ->authorize($allowed)
            ->action(static function (SealedVersion $record): ?StreamedResponse {
                $decision = $record->decision;

                try {
                    $contents = $decision?->evidenceContents();
                } catch (EvidenceFileUnavailable) {
                    $contents = null;
                }

                if ($decision === null || $contents === null) {
                    Notification::make()->danger()->title(Authorizer::label('actions.download.failed'))->send();

                    return null;
                }

                $filename = Str::after(basename((string) $decision->evidence_path), '-');

                return response()->streamDownload(static function () use ($contents): void {
                    echo $contents;
                }, $filename);
            });
    }

    private static function verifyAction(): Action
    {
        $allowed = static fn (mixed $livewire): bool => Authorizer::allows(Authorizer::VIEW, self::owner($livewire));

        return Action::make('verifySealedVersion')
            ->label(Authorizer::label('actions.verify.label'))
            ->icon('heroicon-o-shield-check')
            ->visible($allowed)
            ->authorize($allowed)
            ->action(static function (SealedVersion $record): void {
                $intact = $record->verify() && ($record->decision === null || $record->decision->verify());

                $notification = Notification::make()->title(Authorizer::label($intact ? 'actions.verify.ok' : 'actions.verify.failed'));
                ($intact ? $notification->success() : $notification->danger())->send();
            });
    }

    private static function activityAction(): Action
    {
        $allowed = static fn (mixed $livewire): bool => Authorizer::allows(Authorizer::VIEW, self::owner($livewire));

        return Action::make('viewActivity')
            ->label(Authorizer::label('actions.activity.label'))
            ->icon('heroicon-o-clock')
            ->visible($allowed)
            ->authorize($allowed)
            ->slideOver()
            ->modalHeading(Authorizer::label('actions.activity.heading'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(Authorizer::label('actions.activity.close'))
            ->modalContent(static function (mixed $livewire): \Illuminate\Contracts\View\View {
                $owner = self::owner($livewire);
                $entries = $owner !== null && method_exists($owner, 'auditEntries')
                    ? $owner->auditEntries()->limit(50)->get()
                    : collect();

                /** @var view-string $view */
                $view = 'filament-acceptance::filament.activity';

                return view($view, ['entries' => $entries, 'date' => self::date(...)]);
            });
    }
}
