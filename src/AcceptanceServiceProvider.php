<?php

declare(strict_types=1);

namespace Victorio\Acceptance;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AcceptanceServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-acceptance';

    public function configurePackage(Package $package): void
    {
        // Publish tags follow from ->name() (rule D11): filament-acceptance-config, -migrations, -views,
        // -translations. Drop a has*() call when the package has nothing of that kind.
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            // Every migration explicitly, by its stub filename without the extension. Never edit a
            // migration that is part of a tagged release — add a new one (rule A4).
            // ->hasMigrations(['create_<table_name>_table'])
            ->hasInstallCommand(function (InstallCommand $command): void {
                // php artisan filament-acceptance:install (rule D11)
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageBooted(): void
    {
        // Livewire components, Blade components, route registration, policy binding.
    }
}
