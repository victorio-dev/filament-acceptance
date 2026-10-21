<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Every model the package touches is resolved from here, so the host app can
    | swap any of them for its own subclass (rule D5). Never reference an App\
    | class from package code.
    |
    */

    'models' => [
        // 'example' => \Victorio\Acceptance\Models\Example::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    */

    'tables' => [
        // 'examples' => 'victorio_examples', // snake_case, prefixed, never an App table
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | When the panel uses Filament tenancy, every query on a tenant-owned model
    | is scoped to the current tenant (rule D9). The column below is the foreign
    | key on the package tables pointing at the tenant model.
    |
    */

    'tenancy' => [
        'enabled' => true,
        'foreign_key' => 'tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public routes
    |--------------------------------------------------------------------------
    |
    | Rate limiting is mandatory on every public route (rule E3).
    |
    */

    'public_routes' => [
        'enabled' => false,
        'prefix' => 'p',
        'domain' => null,
        'middleware' => ['web'],
        'rate_limit' => '30,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public tokens
    |--------------------------------------------------------------------------
    |
    | At least 32 bytes of randomness, stored as a hash only, compared in
    | constant time, shown to the user once (rule E1).
    |
    */

    'tokens' => [
        'bytes' => 32,
        'hash' => 'sha256',
        'default_ttl_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access log
    |--------------------------------------------------------------------------
    |
    | Every public access is logged with a hashed IP; tokens and personal data
    | are never logged in plain text (rules E4, E5).
    |
    | The log model uses MassPrunable with the retention period below, and the
    | host app schedules model:prune — the README has to say so (rule E6).
    |
    */

    'access_log' => [
        'enabled' => true,
        'ip_hash_algo' => 'sha256',
        'prune_after_days' => 90,
    ],

];
