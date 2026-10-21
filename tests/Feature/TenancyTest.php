<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\Tenant;

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

it('stores the current Filament tenant as a string key', function (): void {
    $tenant = Tenant::create(['name' => 'Acme']);
    Filament::setTenant($tenant, isQuiet: true);

    expect(sealSample()->fresh()?->tenant_id)->toBe((string) $tenant->id);
});

it('lets an explicit tenant win over the current one', function (): void {
    $current = Tenant::create(['name' => 'Acme']);
    $explicit = Tenant::create(['name' => 'Globex']);
    Filament::setTenant($current, isQuiet: true);

    $version = IntSubject::create(['name' => 'Quote'])->seal('quote', ['v' => 1], tenant: $explicit);

    expect($version->fresh()?->tenant_id)->toBe((string) $explicit->id);
});

it('shows each tenant only its own versions', function (): void {
    $acme = Tenant::create(['name' => 'Acme']);
    $globex = Tenant::create(['name' => 'Globex']);

    Filament::setTenant($acme, isQuiet: true);
    $acmeSubject = IntSubject::create(['name' => 'Acme quote']);
    $acmeVersion = sealSample($acmeSubject);

    Filament::setTenant($globex, isQuiet: true);
    $globexVersion = sealSample();

    expect(SealedVersion::query()->forCurrentTenant()->pluck('id')->all())->toBe([$globexVersion->getKey()])
        ->and($acmeSubject->sealedVersions()->count())->toBe(0)
        ->and($acmeSubject->latestSealedVersion())->toBeNull();

    Filament::setTenant($acme, isQuiet: true);

    expect(SealedVersion::query()->forCurrentTenant()->pluck('id')->all())->toBe([$acmeVersion->getKey()])
        ->and($acmeSubject->sealedVersions()->count())->toBe(1);

    Filament::setTenant(null, isQuiet: true);

    expect(SealedVersion::query()->forCurrentTenant()->count())->toBe(2);
});

it('stores and filters nothing with tenancy disabled', function (): void {
    config()->set('filament-acceptance.tenancy.enabled', false);
    $tenant = Tenant::create(['name' => 'Acme']);
    Filament::setTenant($tenant, isQuiet: true);

    $version = IntSubject::create(['name' => 'Quote'])->seal('quote', ['v' => 1], tenant: $tenant);
    sealSample();

    expect($version->fresh()?->tenant_id)->toBeNull()
        ->and(SealedVersion::query()->forCurrentTenant()->count())->toBe(2);
});
