<?php

declare(strict_types=1);

namespace Victorio\Acceptance;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Approval\Approvables;
use Victorio\Acceptance\Approval\ApprovalField;
use Victorio\Acceptance\Filament\Support\ActionLayout;

/**
 * Optional panel plugin. Registers nothing; the relation manager and the actions work without it.
 * Register it to configure approvals and the look of the decisions table:
 *
 *     AcceptancePlugin::make()
 *         ->approvable(Document::class, title: 'title', fields: [...])
 *         ->rowActions('group')                       // 'icons' (default), 'labels' or 'group'
 *         ->modifyRowActionsUsing(fn (array $actions): array => [$actions['verifySealedVersion']])
 */
final class AcceptancePlugin implements Plugin
{
    private string $rowActions = ActionLayout::ICONS;

    /**
     * @var (Closure(array<string, Action>): array<Action|ActionGroup>)|null
     */
    private ?Closure $modifyRowActionsUsing = null;

    /**
     * @var (Closure(array<string, Action>): array<Action|ActionGroup>)|null
     */
    private ?Closure $modifyHeaderActionsUsing = null;

    /**
     * @var (Closure(array<string, Action>): array<Action|ActionGroup>)|null
     */
    private ?Closure $modifyToolbarActionsUsing = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    /**
     * Which fields of a model are sealed when a record is sent for approval, and the page title
     * (an attribute name or a closure). Wins over the model's own approvalFields().
     *
     *     AcceptancePlugin::make()->approvable(Document::class, title: 'title', fields: [
     *         ApprovalField::make('body')->multiline()->label(['en' => 'Text', 'hu' => 'Szöveg']),
     *     ]);
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<ApprovalField>  $fields
     * @param  string|(Closure(TModel): mixed)|null  $title
     */
    public function approvable(string $model, array $fields, string|Closure|null $title = null): static
    {
        app(Approvables::class)->register($model, $title, $fields);

        return $this;
    }

    /**
     * The plugin of the current panel, or one with the defaults when it is not registered.
     *
     * @internal
     */
    public static function current(): self
    {
        $panel = Filament::getCurrentPanel();
        $id = (new self)->getId();

        if ($panel !== null && $panel->hasPlugin($id)) {
            $plugin = $panel->getPlugin($id);

            if ($plugin instanceof self) {
                return $plugin;
            }
        }

        return new self;
    }

    public function getId(): string
    {
        return 'filament-acceptance';
    }

    public function register(Panel $panel): void
    {
        // $panel->resources([...])->pages([...])->widgets([...]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * How the decisions table shows its row actions: `icons` (default: icon buttons with the label
     * as tooltip, as the table is wide), `labels` (Filament's labelled actions) or `group` (one
     * "more" menu).
     */
    public function rowActions(string $preset): static
    {
        $this->rowActions = ActionLayout::preset($preset);

        return $this;
    }

    public function getRowActions(): string
    {
        return $this->rowActions;
    }

    /**
     * Changes the row actions of the decisions table (download, verify). The closure receives them
     * keyed by name, already styled by rowActions(), and returns the ones to show in that order,
     * modified or with your own added; `group` wraps the result.
     *
     * @param  (Closure(array<string, Action>): array<Action|ActionGroup>)|null  $callback
     */
    public function modifyRowActionsUsing(?Closure $callback): static
    {
        $this->modifyRowActionsUsing = $callback;

        return $this;
    }

    /**
     * @return (Closure(array<string, Action>): array<Action|ActionGroup>)|null
     */
    public function getModifyRowActionsUsing(): ?Closure
    {
        return $this->modifyRowActionsUsing;
    }

    /**
     * Changes the header actions of the decisions table (send for approval, activity), like
     * modifyRowActionsUsing().
     *
     * @param  (Closure(array<string, Action>): array<Action|ActionGroup>)|null  $callback
     */
    public function modifyHeaderActionsUsing(?Closure $callback): static
    {
        $this->modifyHeaderActionsUsing = $callback;

        return $this;
    }

    /**
     * @return (Closure(array<string, Action>): array<Action|ActionGroup>)|null
     */
    public function getModifyHeaderActionsUsing(): ?Closure
    {
        return $this->modifyHeaderActionsUsing;
    }

    /**
     * Adds bulk actions to the decisions table, which has none of its own: the closure receives
     * an empty array; the result goes into Filament's bulk-action menu.
     *
     * @param  (Closure(array<string, Action>): array<Action|ActionGroup>)|null  $callback
     */
    public function modifyToolbarActionsUsing(?Closure $callback): static
    {
        $this->modifyToolbarActionsUsing = $callback;

        return $this;
    }

    /**
     * @return (Closure(array<string, Action>): array<Action|ActionGroup>)|null
     */
    public function getModifyToolbarActionsUsing(): ?Closure
    {
        return $this->modifyToolbarActionsUsing;
    }
}
