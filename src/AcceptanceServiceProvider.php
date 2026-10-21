<?php

declare(strict_types=1);

namespace Victorio\Acceptance;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Victorio\Acceptance\Approval\Approvables;
use Victorio\Acceptance\Commands\EraseCommand;
use Victorio\Acceptance\Http\ApprovalLinkHandler;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Acceptance\Support\AuditLogger;
use Victorio\Acceptance\Support\DecisionRecorder;
use Victorio\Acceptance\Support\Eraser;
use Victorio\PublicLinks\Facades\PublicLinks;

final class AcceptanceServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-acceptance';

    public function configurePackage(Package $package): void
    {
        // Publish tags follow from ->name() (rule D11): filament-acceptance-config, -migrations,
        // -views, -translations.
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            // Every migration explicitly, by its stub filename without the extension. Never edit a
            // migration that is part of a tagged release — add a new one (rule A4).
            ->hasMigrations(['create_sealed_versions_table', 'create_decisions_table', 'create_audit_entries_table'])
            ->hasCommand(EraseCommand::class)
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
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(Eraser::class);
        $this->app->singleton(Approvables::class);
    }

    public function packageBooted(): void
    {
        // The built-in decision page for SendForApprovalAction; a host can register its own
        // handlers for other purposes.
        PublicLinks::handle(
            AcceptanceConfig::versionModel(),
            AcceptanceConfig::approvalPurpose(),
            ApprovalLinkHandler::class,
            'filament-acceptance::filament-acceptance.approval.purpose',
        );
    }
}
