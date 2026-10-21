<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\Support;

use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * The record whose policy decides about an action: the relation manager's owner, else the
 * action's own record — or, for a sealed version, the record it was sealed from.
 *
 * @internal
 */
final class OwnerRecord
{
    public static function resolve(mixed $livewire, ?Model $record = null): ?Model
    {
        $owner = $livewire instanceof RelationManager ? $livewire->getOwnerRecord() : null;

        if ($owner instanceof Model) {
            return $owner;
        }

        if ($record instanceof SealedVersion) {
            $subject = $record->subject;

            return $subject instanceof Model ? $subject : null;
        }

        return $record;
    }
}
