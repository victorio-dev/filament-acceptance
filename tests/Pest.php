<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Victorio\Acceptance\Tests\TestCase;

// RefreshDatabase migrates once per run and wraps every test in a transaction, so the shared
// `testing` database on mysql and pgsql starts clean each time.
uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);
