<?php

declare(strict_types=1);

/**
 * Every shipped language has exactly the English keys, no empty value, and the same
 * :placeholders per key (docs/conventions.md, "Internationalization").
 *
 * @return array<string, mixed>
 */
function translationLines(string $locale): array
{
    $flatten = static function (array $values, string $prefix = '') use (&$flatten): array {
        $lines = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $lines = is_array($value) ? [...$lines, ...$flatten($value, $path)] : [...$lines, $path => $value];
        }

        return $lines;
    };

    return $flatten(require __DIR__.'/../../resources/lang/'.$locale.'/filament-acceptance.php');
}

/**
 * @return list<string>
 */
function placeholders(string $line): array
{
    preg_match_all('/:([A-Za-z_]+)/', $line, $matches);
    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

dataset('shipped locales', fn (): array => array_values(array_diff(
    array_map(basename(...), glob(__DIR__.'/../../resources/lang/*', GLOB_ONLYDIR) ?: []),
    ['en'],
)));

it('ships the eight languages of the convention', function (): void {
    $locales = array_map(basename(...), glob(__DIR__.'/../../resources/lang/*', GLOB_ONLYDIR) ?: []);
    sort($locales);

    expect($locales)->toBe(['de', 'en', 'es', 'fr', 'hu', 'it', 'nl', 'pt_BR'])
        ->and(config('filament-public-links.locales.supported'))->toEqualCanonicalizing($locales);
});

it('has the English keys', function (string $locale): void {
    expect(array_keys(translationLines($locale)))->toEqualCanonicalizing(array_keys(translationLines('en')));
})->with('shipped locales');

it('has no empty value', function (string $locale): void {
    $empty = array_keys(array_filter(translationLines($locale), static fn (mixed $line): bool => ! is_string($line) || trim($line) === ''));

    expect($empty)->toBe([]);
})->with([...array_map(basename(...), glob(__DIR__.'/../../resources/lang/*', GLOB_ONLYDIR) ?: [])]);

it('keeps the placeholders of every line', function (string $locale): void {
    $english = translationLines('en');
    $different = [];

    foreach (translationLines($locale) as $key => $line) {
        if (is_string($line) && isset($english[$key]) && is_string($english[$key]) && placeholders($line) !== placeholders($english[$key])) {
            $different[] = $key;
        }
    }

    expect($different)->toBe([]);
})->with('shipped locales');
