<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 */
final class User extends Authenticatable
{
    protected $table = 'fixture_users';

    protected $guarded = [];
}
