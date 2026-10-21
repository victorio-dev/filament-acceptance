<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Data;

use Victorio\Acceptance\Models\AuditEntry;

/**
 * What an erasure did. `mode` is `anonymize` or `purge`; `record` is the erasure entry it left in
 * the audit log.
 */
final readonly class ErasureReport
{
    public function __construct(
        public string $mode,
        public int $versions,
        public int $decisions,
        public int $evidenceFiles,
        public int $auditEntries,
        public AuditEntry $record,
    ) {}
}
