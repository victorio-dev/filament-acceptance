<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures\Filament\Pages;

use Filament\Resources\Pages\ListRecords;
use Victorio\Acceptance\Tests\Fixtures\Filament\DocumentResource;

final class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;
}
