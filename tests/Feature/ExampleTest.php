<?php

declare(strict_types=1);

it('registers the package config', function (): void {
    expect(config('filament-acceptance'))->toBeArray();
});

it('publishes an English and a Hungarian translation for every key', function (): void {
    $en = require __DIR__.'/../../resources/lang/en/filament-acceptance.php';
    $hu = require __DIR__.'/../../resources/lang/hu/filament-acceptance.php';

    $flatten = function (array $values, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys = is_array($value)
                ? [...$keys, ...$flatten($value, $path)]
                : [...$keys, $path];
        }

        return $keys;
    };

    expect($flatten($hu))->toEqualCanonicalizing($flatten($en));
});
