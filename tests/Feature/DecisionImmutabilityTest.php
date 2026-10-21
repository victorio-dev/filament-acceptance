<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Victorio\Acceptance\Exceptions\EvidenceIsImmutable;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\PrunableSubject;

/**
 * The same paths as for sealed versions (ImmutabilityTest); after each attempt the stored row is
 * unchanged.
 */
function assertDecisionUnchanged(Decision $decision): void
{
    $stored = Decision::query()->whereKey($decision->getKey())->firstOrFail();

    expect($stored->name)->toBe($decision->getOriginal('name'))
        ->and($stored->content_hash)->toBe($decision->getOriginal('content_hash'))
        ->and($stored->verify())->toBeTrue();
}

describe('through the model', function (): void {
    it('refuses save() of a changed attribute', function (): void {
        $decision = decide();
        $decision->forceFill(['name' => 'Someone else']);

        expect(fn (): bool => $decision->save())->toThrow(EvidenceIsImmutable::class, 'Decision');
        assertDecisionUnchanged($decision);
    });

    it('refuses saveQuietly()', function (): void {
        $decision = decide();
        $decision->forceFill(['name' => 'Someone else']);

        expect(fn (): bool => $decision->saveQuietly())->toThrow(EvidenceIsImmutable::class);
        assertDecisionUnchanged($decision);
    });

    it('refuses update()', function (): void {
        $decision = decide();

        expect(fn (): never => $decision->update(['name' => 'Someone else']))->toThrow(EvidenceIsImmutable::class);
        assertDecisionUnchanged($decision);
    });

    it('refuses increment()', function (): void {
        $decision = decide();

        expect(fn (): mixed => $decision->increment('public_link_access_id'))->toThrow(EvidenceIsImmutable::class);
        assertDecisionUnchanged($decision);
    });

    it('refuses delete(), deleteQuietly(), forceDelete() and destroy()', function (Closure $delete): void {
        $decision = decide();

        expect(fn (): mixed => $delete($decision))->toThrow(EvidenceIsPermanent::class, 'Decision');
        assertDecisionUnchanged($decision);
    })->with([
        'delete' => fn (Decision $decision): mixed => $decision->delete(),
        'deleteQuietly' => fn (Decision $decision): mixed => $decision->deleteQuietly(),
        'forceDelete' => fn (Decision $decision): mixed => $decision->forceDelete(),
        'destroy' => fn (Decision $decision): mixed => Decision::destroy($decision->getKey()),
    ]);
});

describe('through the query builder', function (): void {
    it('refuses mass writes', function (Closure $write, string $exception): void {
        $decision = decide();

        expect(fn (): mixed => $write(Decision::query()))->toThrow($exception);
        assertDecisionUnchanged($decision);
    })->with([
        'update' => [fn ($query): mixed => $query->update(['name' => 'x']), EvidenceIsImmutable::class],
        'upsert' => [fn ($query): mixed => $query->upsert([['id' => 'x', 'name' => 'x']], ['id']), EvidenceIsImmutable::class],
        'increment' => [fn ($query): mixed => $query->increment('public_link_access_id'), EvidenceIsImmutable::class],
        'decrement' => [fn ($query): mixed => $query->decrement('public_link_access_id'), EvidenceIsImmutable::class],
        'incrementEach' => [fn ($query): mixed => $query->incrementEach(['public_link_access_id' => 1]), EvidenceIsImmutable::class],
        'touch' => [fn ($query): mixed => $query->touch(), EvidenceIsImmutable::class],
        'delete' => [fn ($query): mixed => $query->delete(), EvidenceIsPermanent::class],
        'forceDelete' => [fn ($query): mixed => $query->forceDelete(), EvidenceIsPermanent::class],
        'truncate' => [fn ($query): mixed => $query->truncate(), EvidenceIsPermanent::class],
    ]);

    it('refuses mass writes through the version relation', function (Closure $write, string $exception): void {
        $decision = decide();
        $version = stored($decision->version ?? throw new LogicException('no version'));

        expect(fn (): mixed => $write($version))->toThrow($exception);
        assertDecisionUnchanged($decision);
    })->with([
        'update' => [fn (SealedVersion $version): mixed => $version->decision()->update(['name' => 'x']), EvidenceIsImmutable::class],
        'delete' => [fn (SealedVersion $version): mixed => $version->decision()->delete(), EvidenceIsPermanent::class],
    ]);
});

describe('retention', function (): void {
    it('is not prunable at all', function (): void {
        $traits = class_uses_recursive(Decision::class);

        expect($traits)->not->toHaveKey(Prunable::class)
            ->and($traits)->not->toHaveKey(MassPrunable::class);
    });

    it('survives model:prune aimed at it, at its version and at its subject', function (): void {
        Exceptions::fake();
        $subject = PrunableSubject::create(['name' => 'Quote']);
        decide($subject->seal('quote', ['v' => 1]));

        Artisan::call('model:prune', ['--model' => [Decision::class, SealedVersion::class, PrunableSubject::class]]);
        Artisan::call('model:prune');

        expect(PrunableSubject::query()->count())->toBe(1)
            ->and(Decision::query()->count())->toBe(1)
            ->and(Decision::query()->sole()->verify())->toBeTrue();
    });

    it('keeps the version of a decision even against a delete below Eloquent', function (): void {
        $decision = decide();

        // In a savepoint: on pgsql a failed statement would abort the test's whole transaction.
        expect(fn (): int => DB::transaction(fn (): int => DB::table('victorio_sealed_versions')->where('id', $decision->sealed_version_id)->delete()))
            ->toThrow(QueryException::class)
            ->and(SealedVersion::query()->count())->toBe(1);
    });
});
