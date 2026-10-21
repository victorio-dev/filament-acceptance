<?php

declare(strict_types=1);

// filament-public-links' own migration, as a host app publishes it: decisions refer to its links
// and access rows, and the end-to-end tests issue real links.
return require __DIR__.'/../../../vendor/victorio/filament-public-links/database/migrations/create_public_links_table.php.stub';
