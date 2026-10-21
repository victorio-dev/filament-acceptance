<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures\Filament;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Victorio\Acceptance\Filament\Actions\SendForApprovalAction;
use Victorio\Acceptance\Filament\RelationManagers\DecisionsRelationManager;
use Victorio\Acceptance\Tests\Fixtures\Document;
use Victorio\Acceptance\Tests\Fixtures\Filament\Pages\EditDocument;
use Victorio\Acceptance\Tests\Fixtures\Filament\Pages\ListDocuments;

final class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('title')]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('title')])
            ->recordActions([SendForApprovalAction::make()]);
    }

    public static function getRelations(): array
    {
        return [DecisionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
