<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Support;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Victorio\Acceptance\Data\DecisionInput;
use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Events\DecisionAccepted;
use Victorio\Acceptance\Events\DecisionDeclined;
use Victorio\Acceptance\Events\DecisionRecorded;
use Victorio\Acceptance\Exceptions\ContentHashMismatch;
use Victorio\Acceptance\Exceptions\DecisionAlreadyMade;
use Victorio\Acceptance\Exceptions\DecisionRefused;
use Victorio\Acceptance\Exceptions\VersionSuperseded;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;
use Victorio\PublicLinks\Support\IpHasher;

/**
 * Records the one decision of a sealed version, race-free (design.md b, "Decisions").
 *
 * @internal
 */
final class DecisionRecorder
{
    /**
     * @param  (Closure(): bool)|null  $transition  the consumer's status change, inside the same
     *                                              transaction; false refuses the decision
     *
     * @throws ValidationException
     * @throws VersionSuperseded
     * @throws ContentHashMismatch
     * @throws DecisionAlreadyMade
     * @throws DecisionRefused
     */
    public function record(
        SealedVersion $version,
        DecisionInput $input,
        ?Request $request = null,
        ?PublicLink $link = null,
        ?PublicLinkAccess $access = null,
        ?Closure $transition = null,
    ): Decision {
        Validator::make($input->toArray(), DecisionInput::rules($input->kind), [], $this->attributeNames())->validate();

        $model = AcceptanceConfig::decisionModel();

        $decision = (new $model)->getConnection()->transaction(function () use ($version, $input, $request, $link, $access, $transition, $model): Decision {
            // Serialises decisions on this version, and a decision with the sealing of a newer
            // version (which writes superseded_at on this row).
            $locked = $version->newQuery()->whereKey($version->getKey())->lockForUpdate()->first();

            if (! $locked instanceof SealedVersion) {
                throw new InvalidArgumentException('Only a stored sealed version can be decided on.');
            }

            if ($locked->isSuperseded()) {
                throw VersionSuperseded::of($locked);
            }

            if (! $locked->verify()) {
                // Ids only: never content, never personal data (rule E5).
                Log::alert('filament-acceptance: a sealed version failed verification; the decision was refused.', [
                    'sealed_version' => $locked->getKey(),
                ]);

                throw ContentHashMismatch::of($locked);
            }

            $existing = $model::query()->where('sealed_version_id', $locked->getKey())->first();

            if ($existing !== null) {
                throw DecisionAlreadyMade::for($locked, $existing);
            }

            if ($transition !== null && $transition() !== true) {
                throw DecisionRefused::for($locked);
            }

            $userAgent = $request?->userAgent();
            $tenantColumn = AcceptanceConfig::tenantColumn();

            $decision = new $model;
            $decision->forceFill([
                'sealed_version_id' => $locked->getKey(),
                $tenantColumn => $locked->getAttribute($tenantColumn),
                'decision' => $input->kind,
                'name' => $input->name,
                'email' => $input->email,
                'comment' => $input->comment,
                'reason' => $input->reason,
                'consent_text' => $input->consentText,
                'locale' => $input->locale,
                'content_hash' => $locked->content_hash,
                // The same HMAC as the access log row of this request (filament-public-links).
                'ip_hash' => $request === null ? null : IpHasher::forRequest($request),
                'user_agent' => $userAgent === null || $userAgent === ''
                    ? null
                    : mb_substr($userAgent, 0, AcceptanceConfig::userAgentMaxLength()),
                'public_link_id' => $link?->getKey(),
                'public_link_access_id' => $access?->getKey(),
                'decided_at' => now(),
            ]);

            try {
                $decision->save();
            } catch (UniqueConstraintViolationException) {
                // The unique index is the backstop; the winner cannot be read in this
                // transaction on every database, so it is not attached.
                throw DecisionAlreadyMade::for($locked);
            }

            return $decision;
        });

        event(new DecisionRecorded($decision));
        event($decision->decision === DecisionKind::Accepted ? new DecisionAccepted($decision) : new DecisionDeclined($decision));

        return $decision;
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        $names = [];

        foreach (['name', 'email', 'consent_text', 'comment', 'reason', 'locale'] as $field) {
            $label = __('filament-acceptance::filament-acceptance.attributes.'.$field);
            $names[$field] = is_string($label) ? $label : $field;
        }

        return $names;
    }
}
