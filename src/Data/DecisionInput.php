<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Data;

use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Support\AcceptanceConfig;

/**
 * What the person outside the panel entered. Validated by Decisions::record() with rules(); the
 * public decision forms use the same rules.
 *
 *     DecisionInput::accept(name: 'Anna Kovács', email: 'anna@example.test', consentText: $label);
 *     DecisionInput::decline(reason: 'Too expensive');
 */
final readonly class DecisionInput
{
    private function __construct(
        public DecisionKind $kind,
        public ?string $name,
        public ?string $email,
        public ?string $consentText,
        public ?string $comment,
        public ?string $reason,
        public string $locale,
    ) {}

    /**
     * Name and e-mail are required; the consent text is the checkbox label exactly as shown.
     */
    public static function accept(string $name, string $email, string $consentText, ?string $comment = null, ?string $locale = null): self
    {
        return new self(DecisionKind::Accepted, self::clean($name), self::clean($email), self::clean($consentText), self::clean($comment), null, $locale ?? app()->getLocale());
    }

    /**
     * Everything is optional; the reason is feedback for the sender.
     */
    public static function decline(?string $name = null, ?string $email = null, ?string $reason = null, ?string $locale = null): self
    {
        return new self(DecisionKind::Declined, self::clean($name), self::clean($email), null, null, self::clean($reason), $locale ?? app()->getLocale());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(DecisionKind $kind): array
    {
        $optional = $kind === DecisionKind::Declined;

        return [
            'name' => [$optional ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => [$optional ? 'nullable' : 'required', 'string', 'email:rfc', 'max:255'],
            'consent_text' => $optional ? ['prohibited'] : ['required', 'string', 'max:2000'],
            'comment' => $optional ? ['prohibited'] : ['nullable', 'string', 'max:'.AcceptanceConfig::maxCommentLength()],
            'reason' => $optional ? ['nullable', 'string', 'max:'.AcceptanceConfig::maxReasonLength()] : ['prohibited'],
            'locale' => ['required', 'string', 'max:8'],
        ];
    }

    /**
     * @return array{name: ?string, email: ?string, consent_text: ?string, comment: ?string, reason: ?string, locale: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'consent_text' => $this->consentText,
            'comment' => $this->comment,
            'reason' => $this->reason,
            'locale' => $this->locale,
        ];
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
