<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\Translator;

/**
 * The hard-coded text check (docs/conventions.md, "Internationalization"): after enable(), every
 * translation renders as ⟦…⟧; untranslated() lists the visible text of a page that is neither
 * inside ⟦ ⟧ nor allowed data (record content, names, dates).
 *
 *     PseudoLocale::enable();
 *     expect(PseudoLocale::untranslated($html, allowed: ['Quote 42']))->toBe([]);
 *
 * Copied from the package skeleton; it moves to victorio/support 0.3 (docs/roadmap.md).
 */
final class PseudoLocale
{
    public static function enable(): void
    {
        $current = app('translator');

        if (! $current instanceof Translator || $current instanceof PseudoTranslator) {
            return;
        }

        $pseudo = new PseudoTranslator($current->getLoader(), $current->getLocale());
        $pseudo->setFallback($current->getFallback());

        app()->instance('translator', $pseudo);
        Facade::clearResolvedInstance('translator');
    }

    /**
     * Visible text nodes and user-facing attributes (alt, title, aria-label, placeholder) that
     * contain letters outside ⟦ ⟧ once the allowed data strings are removed. <style> and
     * <script> are skipped.
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function untranslated(string $html, array $allowed = []): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        $texts = [];

        foreach ($xpath->query('//text()[not(ancestor::style) and not(ancestor::script)]') ?: [] as $node) {
            $texts[] = (string) $node->nodeValue;
        }

        foreach ($xpath->query('//@alt | //@title | //@aria-label | //@placeholder') ?: [] as $attribute) {
            $texts[] = (string) $attribute->nodeValue;
        }

        // Longest first, so an allowed string that contains another is removed whole.
        usort($allowed, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $untranslated = [];

        foreach ($texts as $text) {
            // A translation that contains markup (a link inside a sentence) spans several text
            // nodes: an opening ⟦ marks the rest of its node, a closing ⟧ the start of its node.
            $rest = (string) preg_replace(['/⟦.*?⟧/us', '/⟦[^⟧]*$/us', '/^[^⟦]*⟧/us'], '', $text);
            $rest = str_replace($allowed, '', $rest);

            if (preg_match('/\p{L}/u', $rest) === 1) {
                $untranslated[] = trim((string) preg_replace('/\s+/u', ' ', $text));
            }
        }

        return array_values(array_unique($untranslated));
    }
}
