<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Victorio\Acceptance\Concerns\HasSealedVersions;

/**
 * A subject with soft deletes, as the README recommends.
 *
 * @property int $id
 * @property string $name
 */
final class SoftSubject extends Model
{
    use HasSealedVersions;
    use SoftDeletes;

    protected $table = 'fixture_soft_subjects';

    protected $guarded = [];

    public function approvalConsentText(): string
    {
        return 'I accept the terms of '.$this->name.'.';
    }
}
