<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Concerns\HasSealedVersions;

/**
 * @property string $id
 * @property string $name
 */
final class UuidSubject extends Model
{
    use HasSealedVersions;
    use HasUuids;

    protected $table = 'fixture_uuid_subjects';

    protected $guarded = [];
}
