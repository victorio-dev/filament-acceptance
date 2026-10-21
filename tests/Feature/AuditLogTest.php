<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Exceptions\AuditPropertyNotAllowed;
use Victorio\Acceptance\Facades\AuditLog;
use Victorio\Acceptance\Models\AuditEntry;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\Tenant;
use Victorio\Acceptance\Tests\Fixtures\User;
use Victorio\PublicLinks\Support\IpHasher;

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

it('writes an entry for sealing, the decision and the evidence file, in order, with the tenant', function (): void {
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Acme']);
    Filament::setTenant($tenant, isQuiet: true);
    $user = User::create(['name' => 'Admin']);
    $subject = IntSubject::create(['name' => 'Quote']);

    $version = $subject->seal('quote', ['v' => 1], by: $user);
    $decision = decide($version);
    $decision->storeEvidence('%PDF', 'quote.pdf');

    /** @var list<AuditEntry> $entries */
    $entries = $subject->auditEntries()->get()->reverse()->values()->all();

    expect(array_map(fn (AuditEntry $entry): string => $entry->event, $entries))->toBe(['sealed', 'accepted', 'evidence_stored'])
        ->and(array_unique(array_map(fn (AuditEntry $entry): ?string => $entry->sealed_version_id, $entries)))->toBe([$version->getKey()])
        ->and(array_unique(array_map(fn (AuditEntry $entry): ?string => $entry->tenant_id, $entries)))->toBe([(string) $tenant->id])
        ->and($entries[0]->causer?->is($user))->toBeTrue()
        ->and($entries[0]->properties)->toEqual(['kind' => 'quote', 'number' => 1, 'content_hash' => $version->content_hash])
        ->and($entries[1]->causer_id)->toBeNull()
        ->and($entries[1]->ip_hash)->toBe(IpHasher::hash('203.0.113.7'))
        ->and($entries[1]->properties)->toEqual(['decision_id' => $decision->getKey(), 'content_hash' => $version->content_hash])
        ->and($entries[2]->properties)->toEqual(['decision_id' => $decision->getKey(), 'evidence_hash' => hash('sha256', '%PDF')]);
});

it('writes a declined entry for a decline', function (): void {
    $version = sealSample();
    decide($version, DecisionInput::decline(reason: 'Too expensive'));

    expect(AuditEntry::query()->orderBy('id')->pluck('event')->all())->toBe(['sealed', 'declined']);
});

it('writes nothing when the operation rolls back', function (): void {
    $version = sealSample();

    expect(fn () => decide($version, transition: fn (): bool => false))->toThrow(Exception::class)
        ->and(AuditEntry::query()->pluck('event')->all())->toBe(['sealed']);
});

it('records the consumer\'s own events', function (): void {
    $user = User::create(['name' => 'Admin']);
    $version = sealSample();
    $subject = IntSubject::query()->sole();

    AuditLog::record($subject, 'sent', causer: $user, version: $version, properties: ['channel' => 'mail']);
    AuditLog::record($subject, 'viewed', version: $version, request: visitorRequest());

    expect($subject->auditEntries()->pluck('event')->all())->toBe(['viewed', 'sent', 'sealed'])
        ->and($subject->auditEntries()->first()?->ip_hash)->toBe(IpHasher::hash('203.0.113.7'));
});

it('refuses personal data and secrets in the properties', function (array $properties): void {
    $subject = IntSubject::create(['name' => 'Quote']);

    expect(fn (): AuditEntry => AuditLog::record($subject, 'sent', properties: $properties))->toThrow(AuditPropertyNotAllowed::class)
        ->and(AuditEntry::query()->count())->toBe(0);
})->with([
    'an e-mail' => [['email' => 'anna@example.test']],
    'a name, nested' => [['recipient' => ['Name' => 'Anna']]],
    'an IP' => [['ip' => '203.0.113.7']],
    'a token' => [['token' => 'abc']],
]);

it('shows each tenant only its own entries', function (): void {
    $acme = Tenant::create(['name' => 'Acme']);
    $globex = Tenant::create(['name' => 'Globex']);
    Filament::setTenant($acme, isQuiet: true);
    $subject = IntSubject::create(['name' => 'Quote']);
    sealSample($subject);

    Filament::setTenant($globex, isQuiet: true);

    expect($subject->auditEntries()->count())->toBe(0)
        ->and(AuditEntry::query()->forCurrentTenant()->count())->toBe(0);

    Filament::setTenant($acme, isQuiet: true);

    expect($subject->auditEntries()->count())->toBe(1);
});

it('never holds the raw IP', function (): void {
    $version = sealSample();
    decide($version);
    AuditLog::record(IntSubject::query()->sole(), 'viewed', version: $version, request: visitorRequest());

    foreach (DB::table('victorio_audit_entries')->get() as $row) {
        expect(implode('|', array_map(strval(...), array_filter((array) $row, is_scalar(...)))))->not->toContain('203.0.113.7');
    }

    expect(AuditEntry::query()->get()->toJson())->not->toContain('203.0.113.7');
});
