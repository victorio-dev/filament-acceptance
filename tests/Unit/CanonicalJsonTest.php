<?php

declare(strict_types=1);

use Victorio\Acceptance\Exceptions\NotCanonicalizable;
use Victorio\Acceptance\Support\CanonicalJson;

describe('determinism', function (): void {
    it('sorts object keys, whatever the input order', function (): void {
        expect(CanonicalJson::encode(['b' => 1, 'a' => 2, 'c' => 3]))
            ->toBe('{"a":2,"b":1,"c":3}')
            ->toBe(CanonicalJson::encode(['c' => 3, 'a' => 2, 'b' => 1]));
    });

    it('sorts nested objects and keeps the order of lists', function (): void {
        $one = ['z' => ['y' => [3, 1, 2], 'x' => ['b' => true, 'a' => null]], 'a' => [['k' => 1, 'j' => 2]]];
        $two = ['a' => [['j' => 2, 'k' => 1]], 'z' => ['x' => ['a' => null, 'b' => true], 'y' => [3, 1, 2]]];

        expect(CanonicalJson::encode($one))
            ->toBe('{"a":[{"j":2,"k":1}],"z":{"x":{"a":null,"b":true},"y":[3,1,2]}}')
            ->toBe(CanonicalJson::encode($two));
    });

    it('writes accents and emoji as literal UTF-8', function (): void {
        $json = CanonicalJson::encode(['név' => 'Árvíztűrő tükörfúrógép', 'emoji' => '👍🏽']);

        expect($json)->toBe('{"emoji":"👍🏽","név":"Árvíztűrő tükörfúrógép"}')
            // U+1F44D as its four UTF-8 bytes, not as a 👍 escape.
            ->and(bin2hex($json))->toContain('f09f918d');
    });

    it('orders keys by UTF-16 code units, as RFC 8785 does', function (): void {
        // U+1F600 is D83D DE00 in UTF-16 and sorts before U+FB01; in UTF-8 bytes it would not.
        expect(CanonicalJson::encode(['ﬁ' => 2, '😀' => 1]))->toBe('{"😀":1,"ﬁ":2}');
    });

    it('escapes only quotes, backslashes and control characters', function (): void {
        $value = "q\" b\\ s/ t\t n\n r\r b\x08 f\x0c z\x00 u\x1f d\x7f l\u{2028}";

        expect(CanonicalJson::encode(['s' => $value]))
            ->toBe('{"s":"q\\" b\\\\ s/ t\\t n\\n r\\r b\\b f\\f z\\u0000 u\\u001f d'."\x7f".' l'."\u{2028}".'"}');
    });

    it('writes numeric keys as strings, sorted as strings', function (): void {
        expect(CanonicalJson::encode([10 => 'a', 9 => 'b', 'x' => 1]))->toBe('{"10":"a","9":"b","x":1}');
    });

    it('writes integers, booleans, null and empty arrays', function (): void {
        expect(CanonicalJson::encode([CanonicalJson::MAX_SAFE_INTEGER, -CanonicalJson::MAX_SAFE_INTEGER, 0, true, false, null, []]))
            ->toBe('[9007199254740991,-9007199254740991,0,true,false,null,[]]');
    });

    it('gives a fixed vector its known bytes and SHA-256', function (): void {
        $json = CanonicalJson::encode([
            'total' => 2_057_400,
            'number' => 'Q-2026-0042',
            'lines' => [['quantity' => '1.5', 'net' => 1_620_000, 'name' => 'Árvíztűrő tükörfúrógép 👍']],
            'customer' => ['name' => 'Kovács Anna'],
        ]);

        expect($json)->toBe('{"customer":{"name":"Kovács Anna"},"lines":[{"name":"Árvíztűrő tükörfúrógép 👍","net":1620000,"quantity":"1.5"}],"number":"Q-2026-0042","total":2057400}')
            ->and(hash('sha256', $json))->toBe('a376281212a365d946c35073796656fa95b92c60d8f81666f83bb2ae6e8e8048');
    });
});

describe('floats', function (): void {
    it('refuses a float', function (array $data, string $path): void {
        expect(fn (): string => CanonicalJson::encode($data))
            ->toThrow(NotCanonicalizable::class, $path === '' ? 'the root' : '"'.$path.'"');
    })->with([
        'with a fraction' => [[1.5], '0'],
        'without a fraction' => [['amount' => 1.0], 'amount'],
        'deep in a list' => [['lines' => [['unit_price' => 12.5]]], 'lines.0.unit_price'],
        'infinity' => [['x' => INF], 'x'],
    ]);
});

describe('other values', function (): void {
    it('refuses what has no canonical form', function (array $data, string $message): void {
        expect(fn (): string => CanonicalJson::encode($data))->toThrow(NotCanonicalizable::class, $message);
    })->with([
        'an integer beyond 2^53' => [['n' => CanonicalJson::MAX_SAFE_INTEGER + 1], '±(2^53 - 1)'],
        'an object' => [['o' => new stdClass], 'stdClass is not allowed'],
        'a date' => [['d' => new DateTimeImmutable], 'DateTimeImmutable is not allowed'],
        'invalid UTF-8 in a value' => [['s' => "\xC3\x28"], 'valid UTF-8'],
        'invalid UTF-8 in a key' => [["\xC3\x28" => 1], 'valid UTF-8'],
    ]);
});
