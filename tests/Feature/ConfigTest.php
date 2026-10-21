<?php

declare(strict_types=1);

it('names every model and table in the published config', function (): void {
    $config = require __DIR__.'/../../config/filament-acceptance.php';

    expect(array_keys($config['models']))->toBe(['version', 'decision', 'audit'])
        ->and(array_keys($config['tables']))->toBe(['versions', 'decisions', 'audit']);
});

it('ships an English and a Hungarian translation for every key', function (): void {
    $en = require __DIR__.'/../../resources/lang/en/filament-acceptance.php';
    $hu = require __DIR__.'/../../resources/lang/hu/filament-acceptance.php';

    expect(array_keys($hu['attributes']))->toBe(array_keys($en['attributes']));
});
