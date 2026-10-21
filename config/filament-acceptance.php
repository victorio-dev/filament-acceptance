<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap a model for your own subclass (rule D5). Keep the parent's behaviour:
    | sealed versions rely on it to stay immutable.
    |
    */

    'models' => [
        'version' => Victorio\Acceptance\Models\SealedVersion::class,
        'decision' => Victorio\Acceptance\Models\Decision::class,
        'audit' => Victorio\Acceptance\Models\AuditEntry::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'versions' => 'victorio_sealed_versions',
        'decisions' => 'victorio_decisions',
        'audit' => 'victorio_audit_entries',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | Only takes effect when the current Filament panel has tenancy. A sealed
    | version stores the current tenant (or the one passed to seal()), and
    | forCurrentTenant() limits queries to it (rule D9).
    |
    */

    'tenancy' => [
        'enabled' => true,
        'column' => 'tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Evidence
    |--------------------------------------------------------------------------
    |
    | Decisions store the client IP only as the HMAC that filament-public-links
    | writes into its access log (its access_log.ip_hash_key), so a decision and
    | the request it came from can be matched. Evidence files (e.g. the PDF of an
    | accepted quote) go to this disk — use a private one — and are never
    | deleted by the package.
    |
    */

    'evidence' => [
        'disk' => 'local',
        'path' => 'victorio/evidence',
        'user_agent_max_length' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | Entries older than this are pruned by `model:prune` (rule E6), which the
    | host app schedules — except the evidence chain: entries of a version that
    | has a decision or can still get one (not superseded), and erasure records.
    | null keeps every entry forever.
    |
    */

    'audit' => [
        'prune_after_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Decisions
    |--------------------------------------------------------------------------
    */

    'decisions' => [
        'max_comment_length' => 2000,
        'max_reason_length' => 2000,
    ],

];
