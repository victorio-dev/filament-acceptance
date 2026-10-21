<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Events\DecisionAccepted;
use Victorio\Acceptance\Events\DecisionDeclined;
use Victorio\Acceptance\Events\DecisionRecorded;
use Victorio\Acceptance\Exceptions\ContentHashMismatch;
use Victorio\Acceptance\Exceptions\DecisionAlreadyMade;
use Victorio\Acceptance\Exceptions\DecisionRefused;
use Victorio\Acceptance\Exceptions\VersionSuperseded;
use Victorio\Acceptance\Facades\Decisions;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\Fixtures\Tenant;

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

/**
 * Asserts that recording fails validation on the given field and writes nothing.
 */
function expectInvalid(DecisionInput $input, string $field): void
{
    try {
        decide(input: $input);
        throw new LogicException('The decision was recorded.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect(Decision::query()->count())->toBe(0);
}

describe('validation', function (): void {
    it('accepts with name, e-mail and consent', function (): void {
        $decision = decide();

        expect($decision->decision)->toBe(DecisionKind::Accepted)
            ->and($decision->name)->toBe('Anna Kovács')
            ->and($decision->email)->toBe('anna@example.test')
            ->and($decision->consent_text)->toBe('I accept this quote and its terms.')
            ->and($decision->comment)->toBe('Looking forward to it.')
            ->and($decision->locale)->toBe('hu');
    });

    it('requires a name to accept', function (): void {
        expectInvalid(DecisionInput::accept(name: '  ', email: 'anna@example.test', consentText: 'I accept.'), 'name');
    });

    it('requires an e-mail address to accept', function (): void {
        expectInvalid(DecisionInput::accept(name: 'Anna', email: '', consentText: 'I accept.'), 'email');
    });

    it('requires a valid e-mail address to accept', function (): void {
        expectInvalid(DecisionInput::accept(name: 'Anna', email: 'anna at example', consentText: 'I accept.'), 'email');
    });

    it('requires the consent text to accept', function (): void {
        expectInvalid(DecisionInput::accept(name: 'Anna', email: 'anna@example.test', consentText: ''), 'consent_text');
    });

    it('limits the length of a comment', function (): void {
        config()->set('filament-acceptance.decisions.max_comment_length', 10);

        expectInvalid(DecisionInput::accept(name: 'Anna', email: 'anna@example.test', consentText: 'I accept.', comment: str_repeat('x', 11)), 'comment');
    });

    it('declines with nothing at all', function (): void {
        $decision = decide(input: DecisionInput::decline(name: '', email: null, reason: ''));

        expect($decision->decision)->toBe(DecisionKind::Declined)
            ->and($decision->name)->toBeNull()
            ->and($decision->email)->toBeNull()
            ->and($decision->reason)->toBeNull();
    });

    it('declines with an optional name, e-mail and reason', function (): void {
        $decision = decide(input: DecisionInput::decline(name: 'Anna', email: 'anna@example.test', reason: 'Too expensive.'));

        expect([$decision->name, $decision->email, $decision->reason])->toBe(['Anna', 'anna@example.test', 'Too expensive.']);
    });

    it('limits the length of a reason', function (): void {
        config()->set('filament-acceptance.decisions.max_reason_length', 10);

        expectInvalid(DecisionInput::decline(reason: str_repeat('x', 11)), 'reason');
    });

    it('still validates an e-mail address given on decline', function (): void {
        expectInvalid(DecisionInput::decline(email: 'not-an-address'), 'email');
    });
});

describe('one decision per version', function (): void {
    it('refuses a second decision and keeps the first', function (DecisionInput $second): void {
        $version = sealSample();
        $first = decide($version);

        try {
            decide($version, $second);
            throw new LogicException('A second decision was recorded.');
        } catch (DecisionAlreadyMade $exception) {
            expect($exception->decision?->is($first))->toBeTrue();
        }

        expect(Decision::query()->count())->toBe(1)
            ->and(Decision::query()->sole()->decision)->toBe(DecisionKind::Accepted);
    })->with([
        'accept after accept' => fn (): DecisionInput => acceptance(),
        'decline after accept' => fn (): DecisionInput => DecisionInput::decline(reason: 'Changed my mind.'),
    ]);

    it('lets the unique index refuse a second row written below Eloquent', function (): void {
        $decision = decide();

        // In a savepoint: on pgsql a failed statement would abort the test's whole transaction.
        expect(fn (): bool => DB::transaction(fn (): bool => DB::table('victorio_decisions')->insert([
            'id' => (string) str()->ulid(),
            'sealed_version_id' => $decision->sealed_version_id,
            'decision' => 'declined',
            'locale' => 'en',
            'content_hash' => $decision->content_hash,
            'decided_at' => now(),
        ])))->toThrow(QueryException::class)
            ->and(Decision::query()->count())->toBe(1);
    });
});

it('refuses a decision on a superseded version', function (): void {
    $subject = IntSubject::create(['name' => 'Quote']);
    $old = sealSample($subject);
    sealSample($subject);

    expect(fn (): Decision => decide($old))->toThrow(VersionSuperseded::class)
        ->and(Decision::query()->count())->toBe(0);
});

describe('content hash', function (): void {
    it('stores the hash the version has', function (): void {
        $version = sealSample();
        $decision = decide($version);

        expect($decision->content_hash)->toBe($version->content_hash)
            ->and($decision->content_hash)->toBe(hash('sha256', stored($version)->content))
            ->and($decision->verify())->toBeTrue();
    });

    it('fails verification once the version content is changed afterwards', function (): void {
        $version = sealSample();
        $decision = decide($version);

        DB::table('victorio_sealed_versions')->where('id', $version->getKey())->update(['content' => '{"total":1}']);

        expect($decision->verify())->toBeFalse();
    });

    it('fails verification when content and hash are both replaced afterwards', function (): void {
        $version = sealSample();
        $decision = decide($version);

        DB::table('victorio_sealed_versions')->where('id', $version->getKey())
            ->update(['content' => '{"total":1}', 'content_hash' => hash('sha256', '{"total":1}')]);

        expect(stored($version)->verify())->toBeTrue()
            ->and($decision->verify())->toBeFalse();
    });

    it('refuses to record a decision on a tampered version', function (): void {
        $version = sealSample();
        DB::table('victorio_sealed_versions')->where('id', $version->getKey())->update(['content' => '{"total":1}']);

        expect(fn (): Decision => decide($version))->toThrow(ContentHashMismatch::class)
            ->and(Decision::query()->count())->toBe(0);
    });
});

describe('transition', function (): void {
    it('records when the transition allows it', function (): void {
        $calls = 0;

        decide(transition: function () use (&$calls): bool {
            $calls++;

            return true;
        });

        expect($calls)->toBe(1)
            ->and(Decision::query()->count())->toBe(1);
    });

    it('refuses when the transition says no', function (): void {
        expect(fn (): Decision => decide(transition: fn (): bool => false))->toThrow(DecisionRefused::class)
            ->and(Decision::query()->count())->toBe(0);
    });

    it('rolls back what the transition wrote when the decision fails', function (): void {
        $version = sealSample();
        decide($version);
        $subject = IntSubject::query()->sole();

        expect(fn (): Decision => decide($version, transition: function () use ($subject): bool {
            $subject->update(['name' => 'Changed by a lost decision']);

            return true;
        }))->toThrow(DecisionAlreadyMade::class);

        expect($subject->fresh()?->name)->toBe('Quote');
    });
});

describe('events', function (): void {
    it('announces an acceptance', function (): void {
        Event::fake([DecisionRecorded::class, DecisionAccepted::class, DecisionDeclined::class]);

        $decision = decide();

        Event::assertDispatchedTimes(DecisionRecorded::class, 1);
        Event::assertDispatched(DecisionAccepted::class, fn (DecisionAccepted $event): bool => $event->decision->is($decision));
        Event::assertNotDispatched(DecisionDeclined::class);
    });

    it('announces a decline', function (): void {
        Event::fake([DecisionRecorded::class, DecisionAccepted::class, DecisionDeclined::class]);

        decide(input: DecisionInput::decline());

        Event::assertDispatchedTimes(DecisionRecorded::class, 1);
        Event::assertDispatchedTimes(DecisionDeclined::class, 1);
        Event::assertNotDispatched(DecisionAccepted::class);
    });

    it('announces nothing when the surrounding transaction rolls back', function (): void {
        $announced = [];
        Event::listen([DecisionRecorded::class, DecisionAccepted::class], function (object $event) use (&$announced): void {
            $announced[] = $event::class;
        });

        try {
            DB::transaction(function (): void {
                decide();

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        expect($announced)->toBe([])
            ->and(Decision::query()->count())->toBe(0);
    });

    it('announces nothing for a refused or a duplicate decision', function (): void {
        $version = sealSample();
        decide($version);

        $announced = 0;
        Event::listen([DecisionRecorded::class, DecisionAccepted::class, DecisionDeclined::class], function () use (&$announced): void {
            $announced++;
        });

        expect(fn (): Decision => decide($version))->toThrow(DecisionAlreadyMade::class)
            ->and(fn (): Decision => decide(transition: fn (): bool => false))->toThrow(DecisionRefused::class)
            ->and($announced)->toBe(0);
    });
});

describe('tenancy', function (): void {
    it('copies the tenant of the version', function (): void {
        $tenant = Tenant::create(['name' => 'Acme']);
        Filament::setTenant($tenant, isQuiet: true);
        $version = sealSample();

        // A queued job deciding later has no current tenant; the version's tenant still applies.
        Filament::setTenant(null, isQuiet: true);

        expect(decide($version)->tenant_id)->toBe((string) $tenant->id);
    });

    it('shows each tenant only its own decisions', function (): void {
        $acme = Tenant::create(['name' => 'Acme']);
        $globex = Tenant::create(['name' => 'Globex']);

        Filament::setTenant($acme, isQuiet: true);
        $acmeDecision = decide();

        Filament::setTenant($globex, isQuiet: true);

        expect(Decision::query()->forCurrentTenant()->count())->toBe(0);

        Filament::setTenant($acme, isQuiet: true);

        expect(Decision::query()->forCurrentTenant()->pluck('id')->all())->toBe([$acmeDecision->getKey()]);
    });

    it('filters nothing with tenancy disabled', function (): void {
        config()->set('filament-acceptance.tenancy.enabled', false);
        Filament::setTenant(Tenant::create(['name' => 'Acme']), isQuiet: true);

        $decision = decide();

        expect($decision->tenant_id)->toBeNull()
            ->and(Decision::query()->forCurrentTenant()->count())->toBe(1);
    });
});

it('is reachable from its version', function (): void {
    $version = sealSample();
    $decision = Decisions::record($version, acceptance());

    expect($version->decision?->is($decision))->toBeTrue()
        ->and($decision->version?->is($version))->toBeTrue()
        ->and($decision->ip_hash)->toBeNull()
        ->and(SealedVersion::query()->whereKey($version->getKey())->sole()->decision)->not->toBeNull();
});
