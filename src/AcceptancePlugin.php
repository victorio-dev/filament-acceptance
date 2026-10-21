<?php

declare(strict_types=1);

namespace Victorio\Acceptance;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Approval\Approvables;
use Victorio\Acceptance\Approval\ApprovalField;

final class AcceptancePlugin implements Plugin
{
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
}
