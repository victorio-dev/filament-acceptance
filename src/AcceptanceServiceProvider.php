<?php

declare(strict_types=1);

namespace Victorio\Acceptance;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Victorio\Acceptance\Support\DecisionRecorder;

final class AcceptanceServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-acceptance';

    public function configurePackage(Package $package): void
    {
        // Publish tags follow from ->name() (rule D11): filament-acceptance-config, -migrations,
        // -translations. Views arrive with the public decision forms.
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations()
            // Every migration explicitly, by its stub filename without the extension. Never edit a
            // migration that is part of a tagged release — add a new one (rule A4).
            ->hasMigrations(['create_sealed_versions_table', 'create_decisions_table'])
            ->hasInstallCommand(function (InstallCommand $command): void {
                // php artisan filament-acceptance:install (rule D11)
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DecisionRecorder::class);
    }
}
