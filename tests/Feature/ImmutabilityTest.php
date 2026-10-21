<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Exceptions\SealedVersionIsImmutable;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;

/**
 * What a sealed version guards against (design.md b, "What immutability covers"). Every path is
 * its own case; after each attempt the stored row must be unchanged.
 */
function assertUnchanged(SealedVersion $version): void
{
    $stored = stored($version);

    // Compared with the values as loaded, not with attributes the test changed in memory.
    expect($stored->content)->toBe($version->getOriginal('content'))
        ->and($stored->kind)->toBe('quote')
        ->and($stored->number)->toBe($version->getOriginal('number'))
        ->and($stored->verify())->toBeTrue();
}

describe('through the model', function (): void {
    it('refuses save() of a changed attribute', function (): void {
        $version = sealSample();
        $version->forceFill(['kind' => 'other']);

        expect(fn (): bool => $version->save())->toThrow(SealedVersionIsImmutable::class);
        assertUnchanged($version);
    });

    it('refuses saveQuietly(), which skips the model events', function (): void {
        $version = sealSample();
        $version->forceFill(['content' => '{}']);

        expect(fn (): bool => $version->saveQuietly())->toThrow(SealedVersionIsImmutable::class);
        assertUnchanged($version);
    });

    it('refuses update()', function (): void {
        $version = sealSample();

        expect(fn (): never => $version->update(['kind' => 'other']))->toThrow(SealedVersionIsImmutable::class);
        assertUnchanged($version);
    });

    it('refuses increment()', function (): void {
        $version = sealSample();

        expect(fn (): mixed => $version->increment('number'))->toThrow(SealedVersionIsImmutable::class);
        assertUnchanged($version);
    });

    it('refuses delete()', function (): void {
        $version = sealSample();

        expect(fn (): mixed => $version->delete())->toThrow(EvidenceIsPermanent::class);
        assertUnchanged($version);
    });

    it('refuses deleteQuietly(), which skips the model events', function (): void {
        $version = sealSample();

        expect(fn (): mixed => $version->deleteQuietly())->toThrow(EvidenceIsPermanent::class);
        assertUnchanged($version);
    });

    it('refuses forceDelete()', function (): void {
        $version = sealSample();

        expect(fn (): mixed => $version->forceDelete())->toThrow(EvidenceIsPermanent::class);
        assertUnchanged($version);
    });

    it('refuses destroy()', function (): void {
        $version = sealSample();

        expect(fn (): mixed => SealedVersion::destroy($version->getKey()))->toThrow(EvidenceIsPermanent::class);
        assertUnchanged($version);
    });
});

describe('through the query builder', function (): void {
    it('refuses mass writes', function (Closure $write, string $exception): void {
        $version = sealSample();

        expect(fn (): mixed => $write(SealedVersion::query()))->toThrow($exception);
        assertUnchanged($version);
    })->with([
        'update' => [fn ($query): mixed => $query->update(['kind' => 'other']), SealedVersionIsImmutable::class],
        'upsert' => [fn ($query): mixed => $query->upsert([['id' => 'x', 'kind' => 'other']], ['id']), SealedVersionIsImmutable::class],
        'increment' => [fn ($query): mixed => $query->increment('number'), SealedVersionIsImmutable::class],
        'decrement' => [fn ($query): mixed => $query->decrement('number'), SealedVersionIsImmutable::class],
        'incrementEach' => [fn ($query): mixed => $query->incrementEach(['number' => 1]), SealedVersionIsImmutable::class],
        'touch' => [fn ($query): mixed => $query->touch(), SealedVersionIsImmutable::class],
        'delete' => [fn ($query): mixed => $query->delete(), EvidenceIsPermanent::class],
        'forceDelete' => [fn ($query): mixed => $query->forceDelete(), EvidenceIsPermanent::class],
        'truncate' => [fn ($query): mixed => $query->truncate(), EvidenceIsPermanent::class],
    ]);

    it('refuses mass writes through the subject relation', function (Closure $write, string $exception): void {
        $subject = IntSubject::create(['name' => 'Quote']);
        $version = sealSample($subject);

        expect(fn (): mixed => $write($subject))->toThrow($exception);
        assertUnchanged($version);
    })->with([
        'update' => [fn (IntSubject $subject): mixed => $subject->sealedVersions()->update(['kind' => 'other']), SealedVersionIsImmutable::class],
        'delete' => [fn (IntSubject $subject): mixed => $subject->sealedVersions()->delete(), EvidenceIsPermanent::class],
    ]);
});

describe('below Eloquent (documented, not prevented)', function (): void {
    it('cannot stop DB::table() or toBase(), but verify() detects the change', function (): void {
        $version = sealSample();

        DB::table('victorio_sealed_versions')->where('id', $version->getKey())->update(['content' => '{"total":1}']);
        $viaTable = stored($version);

        expect($viaTable->content)->toBe('{"total":1}')
            ->and($viaTable->verify())->toBeFalse();

        $other = sealSample();
        SealedVersion::query()->whereKey($other->getKey())->toBase()->update(['content' => '{"total":2}']);

        expect(stored($other)->verify())->toBeFalse();
    });
});
