<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Facades\Decisions;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Tests\Fixtures\IntSubject;
use Victorio\Acceptance\Tests\TestCase;

// RefreshDatabase migrates once per run and wraps every test in a transaction, so the shared
// `testing` database on mysql and pgsql starts clean each time.
uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

/**
 * Content with everything that could change on the way through a database: accents, emoji,
 * escapes, a large integer, nested lists and objects.
 *
 * @return array<string, mixed>
 */
function sampleContent(): array
{
    return [
        'number' => 'Q-2026-0042',
        'customer' => ['name' => 'Kovács Anna', 'company' => 'Árvíztűrő Kft. 🚀'],
        'lines' => [
            ['name' => "Line \"one\"\twith\\escapes", 'net' => 1_620_000, 'quantity' => '1.5'],
            ['name' => 'Ünnepi kedvezmény', 'net' => -180_000, 'quantity' => '1'],
        ],
        'terms' => "Payment within 8 days.\nThank you!",
        'total' => 9_000_000_000_000_000,
        'tags' => [],
        'accepted' => null,
    ];
}

function sealSample(?IntSubject $subject = null): SealedVersion
{
    $subject ??= IntSubject::create(['name' => 'Quote']);

    return $subject->seal(kind: 'quote', content: sampleContent(), internal: ['costs' => [1_200_000, 0]]);
}

/**
 * The version as the database has it now.
 */
function stored(SealedVersion $version): SealedVersion
{
    return SealedVersion::query()->whereKey($version->getKey())->firstOrFail();
}

function acceptance(): DecisionInput
{
    return DecisionInput::accept(name: 'Anna Kovács', email: 'anna@example.test', consentText: 'I accept this quote and its terms.', comment: 'Looking forward to it.', locale: 'hu');
}

/**
 * A request as a public visitor sends it, from a fixed documentation address.
 */
function visitorRequest(string $ip = '203.0.113.7'): Request
{
    return Request::create('/p/token/accept', 'POST', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test)']);
}

/**
 * @param  (Closure(): bool)|null  $transition
 */
function decide(?SealedVersion $version = null, ?DecisionInput $input = null, ?Closure $transition = null): Decision
{
    return Decisions::record($version ?? sealSample(), $input ?? acceptance(), visitorRequest(), transition: $transition);
}

/**
 * The accept form as the page submits it, with the default consent text shown.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function acceptForm(array $overrides = []): array
{
    return [
        'name' => 'Anna Kovács',
        'email' => 'anna@example.test',
        'comment' => 'Looking forward to it.',
        'consent' => '1',
        'consent_hash' => hash('sha256', (string) __('filament-acceptance::filament-acceptance.form.consent')),
        ...$overrides,
    ];
}
