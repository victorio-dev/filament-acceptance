<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Lays out a relation manager's actions as the plugin says: the row-action preset and the
 * modify*Using() closures. A closure receives the actions keyed by name and returns them in the
 * order to show, without the ones to leave out, plus any of its own. The same as public-links'
 * (no shared helper yet: it would need a victorio/support release).
 *
 * @internal
 */
final class ActionLayout
{
    public const string LABELS = 'labels';

    public const string ICONS = 'icons';

    public const string GROUP = 'group';

    public const array PRESETS = [self::LABELS, self::ICONS, self::GROUP];

    public static function preset(string $preset): string
    {
        if (! in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException(sprintf('Row actions preset must be one of "%s"; got "%s".', implode('", "', self::PRESETS), $preset));
        }

        return $preset;
    }

    /**
     * Labels keep Filament's table look; icons become icon buttons with their label as tooltip;
     * a group collects them in one "more" menu. The closure runs after the styling and before
     * the grouping, so it can reorder, omit, restyle or add actions.
     *
     * @param  list<Action>  $actions
     * @param  (Closure(array<string, Action>): array<Action|ActionGroup>)|null  $modify
     * @return list<Action|ActionGroup>
     */
    public static function rows(array $actions, string $preset, ?Closure $modify): array
    {
        if ($preset === self::ICONS) {
            foreach ($actions as $action) {
                $action->iconButton()->tooltip(static fn (Action $action): string|Htmlable|null => $action->getLabel());
            }
        }

        $actions = self::modify($actions, $modify);

        return $preset === self::GROUP && $actions !== [] ? [ActionGroup::make($actions)] : $actions;
    }

    /**
     * Bulk actions, in Filament's bulk-action menu.
     *
     * @param  list<Action>  $actions
     * @param  (Closure(array<string, Action>): array<Action|ActionGroup>)|null  $modify
     * @return list<ActionGroup>
     */
    public static function toolbar(array $actions, ?Closure $modify): array
    {
        $actions = self::modify($actions, $modify);

        return $actions === [] ? [] : [BulkActionGroup::make($actions)];
    }

    /**
     * @param  list<Action>  $actions
     * @param  (Closure(array<string, Action>): array<Action|ActionGroup>)|null  $modify
     * @return list<Action|ActionGroup>
     */
    public static function modify(array $actions, ?Closure $modify): array
    {
        if ($modify === null) {
            return $actions;
        }

        $keyed = [];

        foreach ($actions as $action) {
            $keyed[(string) $action->getName()] = $action;
        }

        $result = $modify($keyed);

        if (! is_array($result)) {
            throw new UnexpectedValueException('A modify*ActionsUsing() closure must return an array of actions.');
        }

        foreach ($result as $action) {
            if (! $action instanceof Action && ! $action instanceof ActionGroup) {
                throw new UnexpectedValueException('A modify*ActionsUsing() closure must return only actions or action groups.');
            }
        }

        return array_values($result);
    }
}
