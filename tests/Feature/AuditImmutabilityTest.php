<?php

declare(strict_types=1);

use Victorio\Acceptance\Exceptions\EvidenceIsImmutable;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Models\AuditEntry;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;

/**
 * The same paths as for sealed versions and decisions; after each attempt the entry is unchanged.
 */
function auditEntry(): AuditEntry
{
    sealSample();

    return AuditEntry::query()->sole();
}

function assertEntryUnchanged(AuditEntry $entry): void
{
    $stored = AuditEntry::query()->whereKey($entry->getKey())->sole();

    expect($stored->event)->toBe($entry->getOriginal('event'))
        ->and($stored->properties)->toBe($entry->getOriginal('properties'));
}

it('refuses changes and deletes through the model', function (Closure $write, string $exception): void {
    $entry = auditEntry();

    expect(fn (): mixed => $write($entry))->toThrow($exception);
    assertEntryUnchanged($entry);
})->with([
    'save' => [fn (AuditEntry $entry): mixed => $entry->forceFill(['event' => 'other'])->save(), EvidenceIsImmutable::class],
    'saveQuietly' => [fn (AuditEntry $entry): mixed => $entry->forceFill(['event' => 'other'])->saveQuietly(), EvidenceIsImmutable::class],
    'update' => [fn (AuditEntry $entry): mixed => $entry->update(['event' => 'other']), EvidenceIsImmutable::class],
    'increment' => [fn (AuditEntry $entry): mixed => $entry->increment('causer_id'), EvidenceIsImmutable::class],
    'delete' => [fn (AuditEntry $entry): mixed => $entry->delete(), EvidenceIsPermanent::class],
    'deleteQuietly' => [fn (AuditEntry $entry): mixed => $entry->deleteQuietly(), EvidenceIsPermanent::class],
    'forceDelete' => [fn (AuditEntry $entry): mixed => $entry->forceDelete(), EvidenceIsPermanent::class],
    'destroy' => [fn (AuditEntry $entry): mixed => AuditEntry::destroy($entry->getKey()), EvidenceIsPermanent::class],
]);

it('refuses mass writes through the query builder', function (Closure $write, string $exception): void {
    $entry = auditEntry();

    expect(fn (): mixed => $write(AuditEntry::query()))->toThrow($exception);
    assertEntryUnchanged($entry);
})->with([
    'update' => [fn ($query): mixed => $query->update(['event' => 'x']), EvidenceIsImmutable::class],
    'upsert' => [fn ($query): mixed => $query->upsert([['id' => 1, 'event' => 'x']], ['id']), EvidenceIsImmutable::class],
    'increment' => [fn ($query): mixed => $query->increment('id'), EvidenceIsImmutable::class],
    'decrement' => [fn ($query): mixed => $query->decrement('id'), EvidenceIsImmutable::class],
    'incrementEach' => [fn ($query): mixed => $query->incrementEach(['id' => 1]), EvidenceIsImmutable::class],
    'touch' => [fn ($query): mixed => $query->touch(), EvidenceIsImmutable::class],
    'delete' => [fn ($query): mixed => $query->delete(), EvidenceIsPermanent::class],
    'forceDelete' => [fn ($query): mixed => $query->forceDelete(), EvidenceIsPermanent::class],
    'truncate' => [fn ($query): mixed => $query->truncate(), EvidenceIsPermanent::class],
]);

it('refuses mass writes through the subject relation', function (Closure $write, string $exception): void {
    $entry = auditEntry();
    $subject = IntSubject::query()->sole();

    expect(fn (): mixed => $write($subject))->toThrow($exception);
    assertEntryUnchanged($entry);
})->with([
    'update' => [fn (IntSubject $subject): mixed => $subject->auditEntries()->update(['event' => 'x']), EvidenceIsImmutable::class],
    'delete' => [fn (IntSubject $subject): mixed => $subject->auditEntries()->delete(), EvidenceIsPermanent::class],
]);
