<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Concerns\HasSealedVersions;

/**
 * A subject the host prunes with one DELETE query: its sealed versions must survive.
 *
 * @property string $id
 * @property string $name
 */
final class MassPrunableSubject extends Model
{
    use HasSealedVersions;
    use MassPrunable;

    protected $table = 'fixture_uuid_subjects';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query();
    }
}
