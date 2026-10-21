<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Victorio\Acceptance\Concerns\HasSealedVersions;

/**
 * A subject the host prunes model by model (delete() on each): its sealed versions must survive.
 *
 * @property int $id
 * @property string $name
 */
final class PrunableSubject extends Model
{
    use HasSealedVersions;
    use Prunable;

    protected $table = 'fixture_int_subjects';

    protected $guarded = [];

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query();
    }
}
