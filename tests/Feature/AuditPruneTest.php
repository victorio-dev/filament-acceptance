<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Victorio\Acceptance\Facades\AuditLog;
use Victorio\Acceptance\Facades\Erasure;
use Victorio\Acceptance\Models\AuditEntry;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;

/**
 * Builds a timeline 100 days in the past: a superseded version without a decision, a decided
 * version, the current version without a decision, and an entry without a version.
 *
 * @return array<string, AuditEntry>
 */
function oldTimeline(): array
{
    Carbon::setTestNow(now()->subDays(100));

    $undecided = IntSubject::create(['name' => 'Superseded']);
    $superseded = sealSample($undecided);
    sealSample($undecided);

    $decidedSubject = IntSubject::create(['name' => 'Decided']);
    decide(sealSample($decidedSubject));

    $active = IntSubject::create(['name' => 'Active']);
    sealSample($active);

    $loose = AuditLog::record($active, 'created');
    $supersededEntry = AuditEntry::query()->where('sealed_version_id', $superseded->getKey())->sole();

    Carbon::setTestNow();

    return ['loose' => $loose, 'superseded' => $supersededEntry];
}

function prune(): void
{
    Artisan::call('model:prune', ['--model' => [AuditEntry::class]]);
}

it('prunes old entries that are not part of the evidence chain', function (): void {
    $old = oldTimeline();
    $fresh = AuditLog::record(IntSubject::query()->firstOrFail(), 'reminded');

    prune();

    $left = AuditEntry::query()->pluck('id')->all();

    expect($left)->not->toContain($old['loose']->getKey())
        ->and($left)->not->toContain($old['superseded']->getKey())
        ->and($left)->toContain($fresh->getKey());
});

it('keeps old entries of a decided version', function (): void {
    oldTimeline();

    prune();

    $decided = IntSubject::query()->where('name', 'Decided')->sole();

    expect($decided->auditEntries()->pluck('event')->all())->toBe(['accepted', 'sealed']);
});

it('keeps old entries of a version that can still be decided, and decides it with the full chain', function (): void {
    oldTimeline();
    $active = IntSubject::query()->where('name', 'Active')->sole();
    // The superseded subject's current version is the second one; it stays too.
    $current = IntSubject::query()->where('name', 'Superseded')->sole();

    prune();

    expect($active->auditEntries()->pluck('event')->all())->toBe(['sealed'])
        ->and($current->auditEntries()->pluck('event')->all())->toBe(['sealed']);

    decide($active->latestSealedVersion());

    expect($active->auditEntries()->pluck('event')->all())->toBe(['accepted', 'sealed'])
        ->and($active->auditEntries()->pluck('sealed_version_id')->unique()->count())->toBe(1);
});

it('keeps old erasure records', function (): void {
    Carbon::setTestNow(now()->subDays(100));
    $subject = IntSubject::create(['name' => 'Quote']);
    $record = Erasure::anonymize($subject, reason: 'Request #1')->record;
    Carbon::setTestNow();

    prune();

    expect(AuditEntry::query()->whereKey($record->getKey())->exists())->toBeTrue();
});

it('prunes nothing with prune_after_days = null', function (): void {
    oldTimeline();
    $before = AuditEntry::query()->count();
    config()->set('filament-acceptance.audit.prune_after_days', null);

    prune();

    expect(AuditEntry::query()->count())->toBe($before);
});
