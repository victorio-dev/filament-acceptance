<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

/**
 * "viewer" may see but not send, "stranger" may do neither.
 */
final class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->name !== 'stranger';
    }

    public function update(User $user, Document $document): bool
    {
        return ! in_array($user->name, ['viewer', 'stranger'], true);
    }
}
