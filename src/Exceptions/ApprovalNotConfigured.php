<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A record was sent for approval, but no fields are configured for its model: register it with
 * AcceptancePlugin::approvable() or return ApprovalFields from the model's approvalFields().
 */
final class ApprovalNotConfigured extends LogicException
{
    public static function for(Model $record): self
    {
        return new self(sprintf('No approval fields are configured for %s: use AcceptancePlugin::approvable() or approvalFields() on the model.', $record::class));
    }
}
