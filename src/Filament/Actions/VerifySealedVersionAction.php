<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Victorio\Acceptance\Filament\Support\Authorizer;
use Victorio\Acceptance\Filament\Support\OwnerRecord;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * Re-checks a sealed version (and its decision) against the stored hashes and says whether they
 * are intact. A row action of a table of SealedVersion records. Requires `view` on the record the
 * version was sealed from (or the relation manager's owner).
 *
 * Public API: `make()`, Filament's modifiers and `configureUsing()` work as on any action.
 */
final class VerifySealedVersionAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'verifySealedVersion';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $allowed = static fn (mixed $livewire, SealedVersion $record): bool => Authorizer::allows(Authorizer::VIEW, OwnerRecord::resolve($livewire, $record));

        $this
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
}
