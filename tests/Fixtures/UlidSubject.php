<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Approval\ApprovalField;
use Victorio\Acceptance\Concerns\HasSealedVersions;

/**
 * @property string $id
 * @property string $name
 */
final class UlidSubject extends Model
{
    use HasSealedVersions;
    use HasUlids;

    protected $table = 'fixture_ulid_subjects';

    protected $guarded = [];

    /**
     * @return list<ApprovalField>
     */
    public function approvalFields(): array
    {
        return [ApprovalField::make('name')->label(['en' => 'Name', 'hu' => 'Név'])];
    }
}
