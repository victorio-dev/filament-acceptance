<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Facades;

use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Data\ErasureReport;
use Victorio\Acceptance\Support\Eraser;
use Victorio\Acceptance\Support\ModelKey;

/**
 * The deliberate, logged way to remove evidence when your own legal assessment requires it. The
 * package never calls it; pruning and model deletes never remove evidence.
 *
 *     Erasure::anonymize($quote, reason: 'GDPR request #123', by: auth()->user());
 *     Erasure::purge($quote, reason: 'GDPR request #123', by: auth()->user());
 *
 * anonymize removes the personal data of the decisions and the audit log's IP hashes, but not
 * the sealed content: personal data inside a snapshot (a customer name in a quote) stays, and only
 * purge removes it, together with every version, decision, evidence file and audit entry.
 */
final class Erasure
{
    public static function anonymize(Model $subject, string $reason, ?Model $by = null): ErasureReport
    {
        return app(Eraser::class)->anonymize($subject->getMorphClass(), ModelKey::of($subject), $reason, $by);
    }

    public static function purge(Model $subject, string $reason, ?Model $by = null): ErasureReport
    {
        return app(Eraser::class)->purge($subject->getMorphClass(), ModelKey::of($subject), $reason, $by);
    }
}
