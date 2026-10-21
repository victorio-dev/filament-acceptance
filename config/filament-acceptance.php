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
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'versions' => 'victorio_sealed_versions',
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

];
