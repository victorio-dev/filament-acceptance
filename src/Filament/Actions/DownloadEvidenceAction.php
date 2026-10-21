<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Victorio\Acceptance\Exceptions\EvidenceFileUnavailable;
use Victorio\Acceptance\Filament\Support\Authorizer;
use Victorio\Acceptance\Filament\Support\OwnerRecord;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * Downloads the evidence file stored with a version's decision, after checking its hash; a
 * missing or changed file gives a notification instead. A row action of a table of
 * SealedVersion records, shown only when there is a file. Requires `view` on the record the
 * version was sealed from (or the relation manager's owner).
 *
 * Public API: `make()`, Filament's modifiers and `configureUsing()` work as on any action.
 */
final class DownloadEvidenceAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'downloadEvidence';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $allowed = static fn (mixed $livewire, SealedVersion $record): bool => $record->decision?->evidence_path !== null
            && Authorizer::allows(Authorizer::VIEW, OwnerRecord::resolve($livewire, $record));

        $this
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
}
