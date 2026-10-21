<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Victorio\Acceptance\Exceptions\InternalPartUnreadable;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * Rewrites a stored column below Eloquent, the way someone with database access could.
 */
function tamper(SealedVersion $version, string $column, string $value): SealedVersion
{
    DB::table('victorio_sealed_versions')->where('id', $version->getKey())->update([$column => $value]);

    return stored($version);
}

/**
 * Swaps the app key, as a deployment with a new APP_KEY would.
 *
 * @param  list<string>  $previousKeys
 */
function rotateAppKey(string $key, array $previousKeys = []): void
{
    config()->set('app.key', $key);
    config()->set('app.previous_keys', $previousKeys);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
}

it('verifies an untouched version', function (): void {
    expect(sealSample()->fresh()?->verify())->toBeTrue();
});

it('fails after a single byte of the content changed', function (): void {
    $version = sealSample();
    $position = strpos($version->content, '1620000');
    $changed = substr_replace($version->content, '2', (int) $position, 1);

    expect(strlen($changed))->toBe(strlen($version->content))
        ->and(tamper($version, 'content', $changed)->verify())->toBeFalse();
});

it('fails after a single byte of the stored hash changed', function (): void {
    $version = sealSample();
    $hash = $version->content_hash;
    $changed = substr_replace($hash, $hash[0] === 'a' ? 'b' : 'a', 0, 1);

    expect(tamper($version, 'content_hash', $changed)->verify())->toBeFalse();
});

it('fails for re-encoded content whose hash was fixed up', function (): void {
    $version = sealSample();
    $pretty = (string) json_encode($version->content(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    tamper($version, 'content', $pretty);

    expect(tamper($version, 'content_hash', hash('sha256', $pretty))->verify())->toBeFalse();
});

it('fails for content that is not JSON, even with a matching hash', function (): void {
    $version = sealSample();
    tamper($version, 'content', 'not json');

    expect(tamper($version, 'content_hash', hash('sha256', 'not json'))->verify())->toBeFalse();
});

it('keeps hash and verify intact when the app key changes, but loses the internal part', function (): void {
    $version = sealSample();
    $oldKey = (string) config('app.key');

    rotateAppKey('base64:'.base64_encode(str_repeat('n', 32)));
    $stored = stored($version);

    expect($stored->verify())->toBeTrue()
        ->and($stored->content_hash)->toBe($version->content_hash)
        ->and($stored->content())->toBe($version->content())
        ->and($stored->toArray())->toHaveKey('content')
        ->and(fn (): ?array => $stored->internal())->toThrow(InternalPartUnreadable::class);

    rotateAppKey('base64:'.base64_encode(str_repeat('n', 32)), [$oldKey]);

    expect($stored->internal())->toBe(['costs' => [1_200_000, 0]]);
});
