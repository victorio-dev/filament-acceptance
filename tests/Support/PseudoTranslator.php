<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Support;

use Countable;
use Illuminate\Translation\Translator;

/**
 * Wraps every resolved translation in ⟦ ⟧, so a rendered page shows at a glance which text came
 * from a translation key and which was hard-coded (see PseudoLocale). Missing keys stay as they
 * are, unwrapped, so they are caught too.
 *
 * Copied from the package skeleton; it moves to victorio/support 0.3 (docs/roadmap.md).
 */
final class PseudoTranslator extends Translator
{
    private bool $wrapping = true;

    /**
     * The parameters stay untyped because the parent declares them untyped (rule D2 exception).
     *
     * @param  string  $key
     * @param  array<string, mixed>  $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array<array-key, mixed>
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true): string|array
    {
        $line = parent::get($key, $replace, $locale, $fallback);

        return $this->wrapping && is_string($line) && $line !== $key ? '⟦'.$line.'⟧' : $line;
    }

    /**
     * Pluralisation parses the raw line, so it runs unwrapped and the result is wrapped.
     *
     * @param  string  $key
     * @param  Countable|int|float|array<array-key, mixed>  $number
     * @param  array<string, mixed>  $replace
     * @param  string|null  $locale
     */
    public function choice($key, $number, array $replace = [], $locale = null): string
    {
        $this->wrapping = false;

        try {
            $line = parent::choice($key, $number, $replace, $locale);
        } finally {
            $this->wrapping = true;
        }

        return $line !== $key ? '⟦'.$line.'⟧' : $line;
    }
}
