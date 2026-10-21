<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Victorio\Acceptance\Filament\Actions\SendForApprovalAction;
use Victorio\Acceptance\Filament\Actions\ViewActivityAction;
use Victorio\Acceptance\Tests\Fixtures\Document;
use Victorio\Acceptance\Tests\Fixtures\Filament\DocumentResource;

final class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendForApprovalAction::make(),
            // Urgent documents go to the consumer's own handler ('decide', TestDecisionHandler).
            SendForApprovalAction::make('sendByUrgency')
                ->purpose(fn (Document $record): string => $record->urgent ? 'decide' : 'approval'),
            SendForApprovalAction::make('sendToDecide')->purpose('decide'),
            SendForApprovalAction::make('sendNowhere')->purpose(fn (): string => ''),
            // A package action on its own, outside the decisions table.
            ViewActivityAction::make(),
        ];
    }
}
