<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Victorio\Acceptance\Filament\Actions\SendForApprovalAction;
use Victorio\Acceptance\Tests\Fixtures\Filament\DocumentResource;

final class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [SendForApprovalAction::make()];
    }
}
