<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use JsonException;
use Victorio\Acceptance\Exceptions\NotCanonicalizable;

/**
 * The one serialization a sealed version is hashed over. Identical data gives identical bytes,
 * whatever the key order of the input:
 *
 *     hash('sha256', CanonicalJson::encode($content));
 *
 * The form, also documented in the README so anyone can re-verify a version without this package:
 *
 * - the root is an array: a list becomes a JSON array, anything else a JSON object;
 * - object keys are strings, sorted by their UTF-16 code units (as RFC 8785 sorts them);
 * - values are null, true, false, integers within ±(2^53 − 1), strings and arrays — no floats,
 *   no objects, so money has to be integer minor units;
 * - no whitespace; strings escape only `"`, `\` and control characters (`\b \t \n \f \r`, the
 *   rest as lowercase `\u00xx`); everything else, `/` and U+2028/U+2029 included, is literal UTF-8.
 *
 * For such data the output equals RFC 8785 (JCS). Strings are not Unicode-normalized: "é" as one
 * code point and as "e" + combining accent are different content.
 */
final class CanonicalJson
{
    /**
     * The largest integer that every JSON implementation (IEEE 754 doubles) reads back exactly.
     */
    public const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    private const int STRING_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR;

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws NotCanonicalizable
     */
    public static function encode(array $data): string
    {
        return self::value($data, '');
    }

    /**
     * Values arrive as `mixed` because content is arbitrary data; every type is checked here.
     */
    private static function value(mixed $value, string $path): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            is_int($value) => self::integer($value, $path),
            is_string($value) => self::string($value, $path),
            is_array($value) => array_is_list($value) ? self::list($value, $path) : self::object($value, $path),
            is_float($value) => throw NotCanonicalizable::at($path, 'floats are not allowed, use integers (e.g. minor units for money)'),
            default => throw NotCanonicalizable::at($path, get_debug_type($value).' is not allowed'),
        };
    }

    private static function integer(int $value, string $path): string
    {
        if ($value > self::MAX_SAFE_INTEGER || $value < -self::MAX_SAFE_INTEGER) {
            throw NotCanonicalizable::at($path, 'integers must be within ±(2^53 - 1)');
        }

        return (string) $value;
    }

    private static function string(string $value, string $path): string
    {
        try {
            return json_encode($value, self::STRING_FLAGS);
        } catch (JsonException) {
            throw NotCanonicalizable::at($path, 'strings must be valid UTF-8');
        }
    }

    /**
     * @param  list<mixed>  $values
     */
    private static function list(array $values, string $path): string
    {
        $items = [];

        foreach ($values as $index => $value) {
            $items[] = self::value($value, self::path($path, (string) $index));
        }

        return '['.implode(',', $items).']';
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function object(array $values, string $path): string
    {
        $members = [];

        foreach ($values as $key => $value) {
            // PHP turns numeric string keys into ints; JSON object keys are always strings.
            $key = (string) $key;
            $members[] = [
                'sort' => mb_convert_encoding($key, 'UTF-16BE', 'UTF-8'),
                'json' => self::string($key, self::path($path, $key)).':'.self::value($value, self::path($path, $key)),
            ];
        }

        // RFC 8785 orders keys by UTF-16 code units; big-endian UTF-16 compares byte-wise in that
        // order. Plain UTF-8 byte order differs for characters beyond U+FFFF (emoji).
        usort($members, static fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));

        return '{'.implode(',', array_column($members, 'json')).'}';
    }

    private static function path(string $parent, string $key): string
    {
        return $parent === '' ? $key : $parent.'.'.$key;
    }
}
