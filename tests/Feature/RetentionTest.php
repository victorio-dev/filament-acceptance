<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\MassPrunableSubject;
use Victorio\Acceptance\Tests\Fixtures\PrunableSubject;

it('is not prunable at all', function (): void {
    $traits = class_uses_recursive(SealedVersion::class);

    expect($traits)->not->toHaveKey(Prunable::class)
        ->and($traits)->not->toHaveKey(MassPrunable::class);
});

it('survives model:prune aimed at it', function (): void {
    sealSample();
    sealSample();

    Artisan::call('model:prune', ['--model' => [SealedVersion::class]]);

    expect(SealedVersion::query()->count())->toBe(2);
});

it('survives model:prune of every discovered model', function (): void {
    sealSample();

    Artisan::call('model:prune');

    expect(SealedVersion::query()->count())->toBe(1);
});

it('keeps a subject pruned model by model, and its versions', function (): void {
    Exceptions::fake();
    $subject = PrunableSubject::create(['name' => 'Quote']);
    $subject->seal('quote', ['v' => 1]);
    $subject->seal('quote', ['v' => 2]);

    Artisan::call('model:prune', ['--model' => [PrunableSubject::class]]);

    // Laravel reports the guard's exception for that model and prunes the others.
    Exceptions::assertReported(EvidenceIsPermanent::class);

    expect(PrunableSubject::query()->count())->toBe(1)
        ->and(SealedVersion::query()->count())->toBe(2)
        ->and(SealedVersion::query()->get()->every(fn (SealedVersion $version): bool => $version->verify()))->toBeTrue();
});

it('survives when the subject is mass pruned', function (): void {
    $subject = MassPrunableSubject::create(['id' => (string) Str::uuid(), 'name' => 'Quote']);
    $subject->seal('quote', ['v' => 1]);

    Artisan::call('model:prune', ['--model' => [MassPrunableSubject::class]]);

    expect(MassPrunableSubject::query()->count())->toBe(0)
        ->and(SealedVersion::query()->count())->toBe(1);
});
