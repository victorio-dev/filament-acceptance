<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Victorio\Acceptance\AcceptanceServiceProvider;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\Filament\TestPanelProvider;
use Victorio\Acceptance\Tests\Fixtures\Handlers\TestDecisionHandler;
use Victorio\PublicLinks\Facades\PublicLinks;
use Victorio\PublicLinks\PublicLinksServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        TestDecisionHandler::reset();
        Fixtures\Document::$withPrice = false;
        Fixtures\Document::$consentTexts = [];
        PublicLinks::handle(SealedVersion::class, 'decide', TestDecisionHandler::class);
    }

    /**
     * The $app parameter stays untyped because the parent declares it untyped;
     * adding a native type here would break the signature (rule D2 exception).
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        // The auto-discovered providers of Filament and its dependencies, read from
        // vendor/composer/installed.json (rule B4). All of them exist in Filament 4 and 5.
        return [
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\QueryBuilder\QueryBuilderServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Kirschbaum\PowerJoins\PowerJoinsServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
            \RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider::class,
            PublicLinksServiceProvider::class,
            AcceptanceServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // The internal part of a sealed version is encrypted with the app key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);

        // DB_CONNECTION=mysql|pgsql (`make test DB=...`, CI) keeps Testbench's own connection of
        // that name, whose config file reads DB_HOST, DB_DATABASE and friends. Without it
        // Testbench defaults to its `testing` sqlite connection (foreign keys off), which is
        // replaced below by an in-memory one with foreign keys on.
        if (! in_array($app['config']->get('database.default'), ['sqlite', 'testing'], true)) {
            return;
        }

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            // Enforced like on mysql/pgsql, so cascading deletes behave the same on every driver.
            'foreign_key_constraints' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // The package migrations ship as .php.stub (published by the install command), which
        // the migrator does not pick up; tests/database/migrations wraps them, plus the fixture
        // tables.
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
