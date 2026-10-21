<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
