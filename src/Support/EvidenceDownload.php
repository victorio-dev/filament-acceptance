<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Victorio\Acceptance\Models\Decision;

/**
 * The headers of an evidence download, the same on the public page and in Filament: the file's
 * own type and name, always as an attachment. Only inert types keep their type; everything a
 * browser could run on the app's domain (HTML, SVG, XML, scripts, ...) goes out as
 * `application/octet-stream`, and `nosniff` stops the browser from guessing otherwise.
 *
 * @internal
 */
final class EvidenceDownload
{
    /**
     * Types a browser only displays or hands to a viewer; prefixes end in a dot.
     */
    private const array INERT_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.',
        'application/vnd.oasis.opendocument.',
    ];

    /**
     * @return array<string, string>
     */
    public static function headers(Decision $decision): array
    {
        $filename = $decision->evidenceFilename() ?? 'evidence';

        return [
            'Content-Type' => self::servedType($decision->evidenceMimeType() ?? 'application/octet-stream'),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, self::asciiFallback($filename)),
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    public static function servedType(string $type): string
    {
        $type = strtolower(trim(Str::before($type, ';')));

        foreach (self::INERT_TYPES as $inert) {
            if ($type === $inert || (str_ends_with($inert, '.') && str_starts_with($type, $inert))) {
                return $type;
            }
        }

        return 'application/octet-stream';
    }

    private static function asciiFallback(string $filename): string
    {
        // makeDisposition() refuses "%" and "/" or "\" in the fallback.
        $fallback = str_replace(['%', '/', '\\'], '-', Str::ascii($filename));

        return $fallback === '' ? 'evidence' : $fallback;
    }
}
