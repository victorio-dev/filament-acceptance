<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Approval\ApprovalField;
use Victorio\Acceptance\Concerns\HasSealedVersions;

/**
 * @property int $id
 * @property string $name
 */
final class IntSubject extends Model
{
    use HasSealedVersions;

    protected $table = 'fixture_int_subjects';

    protected $guarded = [];

    /**
     * @return list<ApprovalField>
     */
    public function approvalFields(): array
    {
        return [ApprovalField::make('name')->label(['en' => 'Name', 'hu' => 'Név'])];
    }
}
