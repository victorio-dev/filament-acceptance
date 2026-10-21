<?php

declare(strict_types=1);

use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\SoftSubject;

it('refuses to force delete a soft-deletable subject with sealed versions', function (): void {
    $subject = SoftSubject::create(['name' => 'Quote']);
    $subject->seal('quote', ['v' => 1]);

    expect(fn (): ?bool => $subject->forceDelete())->toThrow(EvidenceIsPermanent::class)
        ->and(SoftSubject::withTrashed()->count())->toBe(1)
        ->and(SealedVersion::query()->count())->toBe(1);
});

it('soft deletes a subject with sealed versions', function (): void {
    $subject = SoftSubject::create(['name' => 'Quote']);
    $subject->seal('quote', ['v' => 1]);

    $subject->delete();

    expect(SoftSubject::query()->count())->toBe(0)
        ->and(SoftSubject::withTrashed()->count())->toBe(1)
        ->and(SealedVersion::query()->sole()->verify())->toBeTrue();
});

it('refuses to delete a subject without soft deletes once it has sealed versions', function (): void {
    $subject = IntSubject::create(['name' => 'Quote']);
    $subject->seal('quote', ['v' => 1]);

    expect(fn (): ?bool => $subject->delete())->toThrow(EvidenceIsPermanent::class)
        ->and(IntSubject::query()->count())->toBe(1);
});

it('checks every tenant\'s versions, not only the current one\'s', function (): void {
    $subject = IntSubject::create(['name' => 'Quote']);
    $subject->seal('quote', ['v' => 1], tenant: Victorio\Acceptance\Tests\Fixtures\Tenant::create(['name' => 'Acme']));

    Filament\Facades\Filament::setTenant(Victorio\Acceptance\Tests\Fixtures\Tenant::create(['name' => 'Globex']), isQuiet: true);

    expect(fn (): ?bool => $subject->delete())->toThrow(EvidenceIsPermanent::class);

    Filament\Facades\Filament::setTenant(null, isQuiet: true);
});

it('deletes a subject without sealed versions, with or without soft deletes', function (): void {
    $soft = SoftSubject::create(['name' => 'Draft']);
    $plain = IntSubject::create(['name' => 'Draft']);

    $soft->forceDelete();
    $plain->delete();

    expect(SoftSubject::withTrashed()->count())->toBe(0)
        ->and(IntSubject::query()->count())->toBe(0);
});
