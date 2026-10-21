<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Victorio\Acceptance\Events\VersionSealed;
use Victorio\Acceptance\Exceptions\NotCanonicalizable;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Support\CanonicalJson;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\UlidSubject;
use Victorio\Acceptance\Tests\Fixtures\User;
use Victorio\Acceptance\Tests\Fixtures\UuidSubject;

it('numbers the versions of a subject and supersedes the previous one', function (Closure $make): void {
    /** @var IntSubject|UlidSubject|UuidSubject $subject */
    $subject = $make();
    $other = $make();

    $first = $subject->seal('quote', ['v' => 1]);
    $second = $subject->seal('quote', ['v' => 2]);
    $third = $subject->seal('quote', ['v' => 3]);
    $otherFirst = $other->seal('quote', ['v' => 1]);

    expect([$first->number, $second->number, $third->number, $otherFirst->number])->toBe([1, 2, 3, 1])
        ->and($first->fresh()?->isSuperseded())->toBeTrue()
        ->and($second->fresh()?->isSuperseded())->toBeTrue()
        ->and($third->fresh()?->isSuperseded())->toBeFalse()
        ->and($otherFirst->fresh()?->isSuperseded())->toBeFalse()
        ->and($subject->sealedVersions()->pluck('number')->all())->toBe([3, 2, 1])
        ->and($subject->latestSealedVersion()?->is($third))->toBeTrue()
        ->and($third->fresh()?->subject?->is($subject))->toBeTrue();
})->with([
    'int key' => fn (): Model => IntSubject::create(['name' => 'Quote']),
    'ULID key' => fn (): Model => UlidSubject::create(['name' => 'Quote']),
    'UUID key' => fn (): Model => UuidSubject::create(['name' => 'Quote']),
]);

it('stores the canonical content, its hash, the kind and who sealed it', function (): void {
    $user = User::create(['name' => 'Admin']);
    $version = IntSubject::create(['name' => 'Quote'])->seal('quote', ['b' => 2, 'a' => 1], by: $user);

    expect($version->content)->toBe('{"a":1,"b":2}')
        ->and($version->content_hash)->toBe(hash('sha256', '{"a":1,"b":2}'))
        ->and($version->kind)->toBe('quote')
        ->and($version->fresh()?->sealedBy?->is($user))->toBeTrue()
        ->and($version->sealed_at)->not->toBeNull();
});

it('refuses content with a float and writes nothing', function (): void {
    $subject = IntSubject::create(['name' => 'Quote']);

    expect(fn (): SealedVersion => $subject->seal('quote', ['total' => 12.5]))->toThrow(NotCanonicalizable::class)
        ->and(SealedVersion::query()->count())->toBe(0);
});

it('refuses to seal a subject that was never saved', function (): void {
    expect(fn (): SealedVersion => (new IntSubject(['name' => 'Quote']))->seal('quote', ['v' => 1]))
        ->toThrow(InvalidArgumentException::class, 'Save the subject');
});

it('lets the database refuse a second version with the same number', function (): void {
    $version = sealSample();

    // In a savepoint: on pgsql a failed statement would abort the test's whole transaction.
    expect(fn (): bool => DB::transaction(fn (): bool => DB::table('victorio_sealed_versions')->insert([
        'id' => (string) str()->ulid(),
        'subject_type' => $version->subject_type,
        'subject_id' => $version->subject_id,
        'kind' => 'quote',
        'number' => $version->number,
        'content' => '[]',
        'content_hash' => hash('sha256', '[]'),
        'sealed_at' => now(),
    ])))->toThrow(QueryException::class);
});

it('keeps the hash and the bytes of the content after a database round trip', function (): void {
    $version = sealSample();
    $stored = stored($version);

    expect($stored->content)->toBe(CanonicalJson::encode(sampleContent()))
        ->and(hash('sha256', $stored->content))->toBe($version->content_hash)
        ->and($stored->content_hash)->toBe($version->content_hash)
        ->and(CanonicalJson::encode($stored->content()))->toBe($stored->content)
        ->and($stored->verify())->toBeTrue();
});

it('encrypts the internal part and never puts it in arrays or JSON', function (): void {
    $version = sealSample();
    $raw = DB::table('victorio_sealed_versions')->where('id', $version->getKey())->value('internal');

    expect($raw)->toBeString()
        ->and($raw)->not->toContain('1200000')
        ->and($version->fresh()?->internal())->toBe(['costs' => [1_200_000, 0]])
        ->and($version->fresh()?->toArray())->not->toHaveKey('internal')
        ->and((string) $version->fresh()?->toJson())->not->toContain('1200000')
        ->and((string) $version->fresh()?->toJson())->not->toContain((string) $raw);
});

it('announces a sealed version', function (): void {
    Event::fake([VersionSealed::class]);

    $version = sealSample();

    Event::assertDispatchedTimes(VersionSealed::class, 1);
    Event::assertDispatched(VersionSealed::class, fn (VersionSealed $event): bool => $event->version->is($version));
});

it('does not announce a version whose transaction rolled back', function (): void {
    $announced = 0;
    Event::listen(VersionSealed::class, function () use (&$announced): void {
        $announced++;
    });

    try {
        DB::transaction(function (): void {
            sealSample();

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($announced)->toBe(0)
        ->and(SealedVersion::query()->count())->toBe(0);
});
