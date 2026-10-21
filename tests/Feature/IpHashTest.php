<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\withServerVariables;

use Victorio\Acceptance\Events\DecisionAccepted;
use Victorio\Acceptance\Events\DecisionRecorded;
use Victorio\Acceptance\Tests\Fixtures\Handlers\DecideVersion;
use Victorio\PublicLinks\Builders\PendingPublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;
use Victorio\PublicLinks\Support\IpHasher;

const VISITOR_IP = '203.0.113.7';

it('stores the IP only as the access log HMAC', function (): void {
    $decision = decide();

    expect($decision->ip_hash)->toBe(IpHasher::hash(VISITOR_IP))
        ->and($decision->user_agent)->toBe('Mozilla/5.0 (Test)');
});

it('uses the access log key when one is configured', function (): void {
    config()->set('filament-public-links.access_log.ip_hash_key', 'shared-key');

    expect(decide()->ip_hash)->toBe(hash_hmac('sha256', VISITOR_IP, 'shared-key'));
});

it('never stores, serializes or announces the raw IP', function (): void {
    $events = [];
    Event::listen([DecisionRecorded::class, DecisionAccepted::class], function (object $event) use (&$events): void {
        $events[] = serialize($event);
    });

    $decision = decide();
    $row = (array) DB::table('victorio_decisions')->where('id', $decision->getKey())->first();

    expect(implode('|', array_map(strval(...), array_filter($row, is_scalar(...)))))->not->toContain(VISITOR_IP)
        ->and(json_encode($decision->toArray()))->not->toContain(VISITOR_IP)
        ->and($decision->toJson())->not->toContain(VISITOR_IP)
        ->and($events)->toHaveCount(2)
        ->and(implode('', $events))->not->toContain(VISITOR_IP);
});

it('matches the access row of the request it came from, through public-links', function (): void {
    $version = sealSample();
    $issued = (new PendingPublicLink($version, 'decide'))->issue();

    withServerVariables(['REMOTE_ADDR' => VISITOR_IP])
        ->post('/p/'.$issued->token.'/accept')
        ->assertOk();

    $access = PublicLinkAccess::query()->sole();
    $decision = DecideVersion::$lastDecision;

    expect($decision?->ip_hash)->toBe($access->ip_hash)
        ->and($decision?->public_link_access_id)->toBe($access->getKey())
        ->and($decision?->public_link_id)->toBe($issued->link->getKey())
        ->and($access->outcome)->toBe('action');
});
