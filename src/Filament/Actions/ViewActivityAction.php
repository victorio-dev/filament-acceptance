<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\Actions;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Victorio\Acceptance\Filament\Support\Authorizer;
use Victorio\Acceptance\Filament\Support\OwnerRecord;

/**
 * The record's activity (sealed, sent, accepted, …, newest first, the last 50) in a slide-over.
 * A header action of the decisions table, or a page action of a record that uses
 * HasSealedVersions. Requires `view` on the record.
 *
 * Public API: `make()`, Filament's modifiers and `configureUsing()` work as on any action.
 */
final class ViewActivityAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'viewActivity';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $allowed = static fn (mixed $livewire, ?Model $record): bool => Authorizer::allows(Authorizer::VIEW, OwnerRecord::resolve($livewire, $record));

        $this
            ->label(Authorizer::label('actions.activity.label'))
            ->icon('heroicon-o-clock')
            ->visible($allowed)
            ->authorize($allowed)
            ->slideOver()
            ->modalHeading(Authorizer::label('actions.activity.heading'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(Authorizer::label('actions.activity.close'))
            ->modalContent(static function (mixed $livewire, ?Model $record): View {
                $owner = OwnerRecord::resolve($livewire, $record);
                $entries = $owner !== null && method_exists($owner, 'auditEntries')
                    ? $owner->auditEntries()->limit(50)->get()
                    : collect();

                /** @var view-string $view */
                $view = 'filament-acceptance::filament.activity';

                return view($view, ['entries' => $entries, 'date' => self::date(...)]);
            });
    }

    private static function date(DateTimeInterface $date): string
    {
        $date = CarbonImmutable::instance($date)->setTimezone(FilamentTimezone::get());
        $localized = $date->locale(App::getLocale());

        return ($localized instanceof CarbonImmutable ? $localized : $date)->isoFormat('LLL');
    }
}
