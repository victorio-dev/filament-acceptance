<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

/**
 * @param  array<string, mixed>  $parameters
 */
function eraseCommand(array $parameters): PendingCommand
{
    $pending = artisan('filament-acceptance:erase', $parameters);

    if (! $pending instanceof PendingCommand) {
        throw new LogicException('Mocking the console output is off.');
    }

    return $pending;
}

use Victorio\Acceptance\Facades\AuditLog;
use Victorio\Acceptance\Facades\Erasure;
use Victorio\Acceptance\Models\AuditEntry;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\SoftSubject;
use Victorio\Acceptance\Tests\Fixtures\User;

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * A subject with an accepted version, its evidence file and a viewed entry with an IP hash.
 *
 * @return array{subject: IntSubject, version: SealedVersion, decision: Decision}
 */
function decidedSubject(string $name = 'Quote'): array
{
    $subject = IntSubject::create(['name' => $name]);
    $version = $subject->seal('quote', ['customer' => 'Kovács Anna', 'total' => 100]);
    AuditLog::record($subject, 'viewed', version: $version, request: visitorRequest());
    $decision = decide($version);
    $decision->storeEvidence('%PDF', 'quote.pdf');

    return ['subject' => $subject, 'version' => $version, 'decision' => $decision];
}

it('erases nothing through pruning, deletes or force deletes', function (): void {
    ['subject' => $subject] = decidedSubject();
    $soft = SoftSubject::create(['name' => 'Soft']);
    $soft->seal('quote', ['v' => 1]);

    Artisan::call('model:prune', ['--model' => [AuditEntry::class, Decision::class, SealedVersion::class]]);
    rescue(fn () => $subject->delete(), report: false);
    rescue(fn () => $soft->forceDelete(), report: false);

    expect(SealedVersion::query()->count())->toBe(2)
        ->and(Decision::query()->sole()->name)->toBe('Anna Kovács')
        ->and(Decision::query()->sole()->verifyEvidence())->toBeTrue()
        ->and(AuditEntry::query()->where('event', AuditEntry::ERASURE)->count())->toBe(0);
});

it('anonymizes the decisions, logs itself, and keeps everything verifiable', function (): void {
    ['subject' => $subject, 'version' => $version, 'decision' => $decision] = decidedSubject();
    $admin = User::create(['name' => 'Admin']);

    $report = Erasure::anonymize($subject, reason: 'GDPR request #123', by: $admin);

    $stored = Decision::query()->sole();

    expect([$stored->name, $stored->email, $stored->comment, $stored->reason, $stored->user_agent, $stored->ip_hash])
        ->toBe([null, null, null, null, null, null])
        ->and($stored->verify())->toBeTrue()
        ->and(stored($version)->verify())->toBeTrue()
        ->and($stored->evidenceContents())->toBe('%PDF')
        // The sealed content is untouched: personal data inside the snapshot stays.
        ->and(stored($version)->content())->toMatchArray(['customer' => 'Kovács Anna'])
        ->and(AuditEntry::query()->whereNotNull('ip_hash')->count())->toBe(0)
        ->and($report->mode)->toBe('anonymize')
        ->and($report->decisions)->toBe(1)
        ->and($report->record->event)->toBe(AuditEntry::ERASURE)
        ->and($report->record->causer?->is($admin))->toBeTrue()
        ->and($report->record->properties)->toMatchArray(['mode' => 'anonymize', 'reason' => 'GDPR request #123', 'decisions' => 1]);
});

it('purges a subject\'s evidence, logs itself, and leaves the rest verifiable', function (): void {
    ['subject' => $subject, 'version' => $version, 'decision' => $decision] = decidedSubject();
    ['version' => $otherVersion, 'decision' => $otherDecision] = decidedSubject('Other quote');
    $path = (string) $decision->evidence_path;

    $report = Erasure::purge($subject, reason: 'GDPR request #124');

    expect($subject->sealedVersions()->count())->toBe(0)
        ->and(Decision::query()->whereKey($decision->getKey())->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and($subject->auditEntries()->pluck('event')->all())->toBe([AuditEntry::ERASURE])
        ->and($report->record->properties)->toMatchArray([
            'mode' => 'purge',
            'versions' => 1,
            'decisions' => 1,
            'evidence_files' => 1,
            'audit_entries' => 4,
            'removed_versions' => [['number' => 1, 'content_hash' => $version->content_hash]],
        ])
        // The other subject's chain is intact.
        ->and(stored($otherVersion)->verify())->toBeTrue()
        ->and(Decision::query()->whereKey($otherDecision->getKey())->sole()->verify())->toBeTrue()
        ->and(Storage::disk('local')->exists((string) $otherDecision->evidence_path))->toBeTrue();

    // With the evidence gone, the subject can be deleted.
    $subject->delete();

    expect(IntSubject::query()->whereKey($subject->getKey())->exists())->toBeFalse();
});

it('keeps earlier erasure records when purging', function (): void {
    ['subject' => $subject] = decidedSubject();

    Erasure::anonymize($subject, reason: 'Request #1');
    Erasure::purge($subject, reason: 'Request #2');

    expect(AuditEntry::query()->where('event', AuditEntry::ERASURE)->count())->toBe(2);
});

it('needs a reason', function (Closure $erase): void {
    ['subject' => $subject] = decidedSubject();

    expect(fn () => $erase($subject))->toThrow(InvalidArgumentException::class, 'reason')
        ->and(Decision::query()->sole()->name)->toBe('Anna Kovács');
})->with([
    'anonymize' => fn (IntSubject $subject): mixed => Erasure::anonymize($subject, reason: '  '),
    'purge' => fn (IntSubject $subject): mixed => Erasure::purge($subject, reason: ''),
]);

it('erases through the artisan command, by type and id, only when confirmed', function (): void {
    ['subject' => $subject] = decidedSubject();
    $type = $subject->getMorphClass();
    $id = (string) $subject->getKey();

    eraseCommand(['type' => $type, 'id' => $id, '--reason' => 'Request #3'])
        ->expectsConfirmation(sprintf('This will anonymize the decisions (name, e-mail, comment, reason, user agent, IP hash) of %s %s. It cannot be undone. Continue?', $type, $id), 'no')
        ->assertFailed();

    expect(Decision::query()->sole()->name)->toBe('Anna Kovács');

    eraseCommand(['type' => $type, 'id' => $id, '--reason' => 'Request #3', '--purge' => true, '--force' => true])
        ->assertSuccessful();

    expect(SealedVersion::query()->count())->toBe(0)
        ->and(AuditEntry::query()->sole()->event)->toBe(AuditEntry::ERASURE);
});

it('refuses the command without a reason', function (): void {
    eraseCommand(['type' => 'x', 'id' => '1', '--force' => true])->assertExitCode(2);
});

it('leaves no raw IP in the erasure record', function (): void {
    ['subject' => $subject] = decidedSubject();

    Erasure::purge($subject, reason: 'Request #4');

    expect((string) json_encode(DB::table('victorio_audit_entries')->get()))->not->toContain('203.0.113.7');
});
