<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Filament\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use UnexpectedValueException;
use Victorio\Acceptance\Approval\ApprovalContent;
use Victorio\Acceptance\Concerns\HasSealedVersions;
use Victorio\Acceptance\Filament\Support\Authorizer;
use Victorio\Acceptance\Models\SealedVersion;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Acceptance\Support\AuditLogger;
use Victorio\Acceptance\Support\Sealer;
use Victorio\PublicLinks\Builders\PendingPublicLink;
use Victorio\PublicLinks\Data\IssuedPublicLink;
use Victorio\PublicLinks\Filament\Actions\IssuePublicLinkAction;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\Support\Database\ModelKey;

/**
 * "Send for approval" for any resource whose model uses HasSealedVersions, as a page or table row
 * action — no handler to write:
 *
 *     protected function getHeaderActions(): array
 *     {
 *         return [SendForApprovalAction::make()];
 *     }
 *
 * It asks for the expiry and the language, seals the record's approval fields in that language,
 * issues a decision link to the new version (no usage limit) and shows its URL in public-links'
 * URL modal. Requires `update` on the record.
 *
 * The link goes to the built-in decision page (the `approval.purpose` config key). To send some
 * records to your own DecisionLinkHandler, give a purpose per record:
 *
 *     SendForApprovalAction::make()
 *         ->purpose(fn (Document $record): string => $record->type === 'brief' ? 'project-brief' : 'approval')
 *
 * An IssuePublicLinkAction, so its methods (modalHeading(), fields(), …) work here too.
 */
final class SendForApprovalAction extends IssuePublicLinkAction
{
    /**
     * Filament injects the closure's parameters by name or type, so any record type goes: the
     * parameter is typed `never`.
     *
     * @var string|(Closure(never): mixed)|null
     */
    private string|Closure|null $approvalPurpose = null;

    public static function getDefaultName(): string
    {
        return 'sendForApproval';
    }

    /**
     * The public-links purpose of the decision link: a string, or a closure that receives the
     * record (as `$record` or by its type) and returns one. Register a handler for
     * SealedVersion under every purpose you return. Default: the `approval.purpose` config key,
     * the built-in decision page.
     *
     * @param  string|(Closure(never): mixed)  $purpose
     */
    public function purpose(string|Closure $purpose): static
    {
        $this->approvalPurpose = $purpose;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(Authorizer::label('actions.send.label'))
            ->modalHeading('filament-acceptance::filament-acceptance.filament.actions.send.heading')
            ->urlModalHeading('filament-acceptance::filament-acceptance.filament.actions.send.url_heading')
            ->modalSubmitActionLabel(Authorizer::label('actions.send.submit'))
            ->icon('heroicon-o-check-badge')
            ->fields(['expiry', 'locale'])
            ->issueUsing(fn (Model $record, array $data, Closure $pending): IssuedPublicLink => $this->send($record, $data, $pending))
            ->linkOwnedUsing(static fn (Model $record, PublicLink $link): bool => self::owns($record, $link));
    }

    /**
     * @throws UnexpectedValueException when the purpose closure returns no string
     */
    private function purposeFor(Model $record): string
    {
        if ($this->approvalPurpose === null) {
            return AcceptanceConfig::approvalPurpose();
        }

        $purpose = $this->evaluate($this->approvalPurpose, ['record' => $record], [Model::class => $record, $record::class => $record]);

        if (! is_string($purpose) || $purpose === '') {
            throw new UnexpectedValueException('SendForApprovalAction::purpose() must give a non-empty string.');
        }

        return $purpose;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Closure(Model, string): PendingPublicLink  $pending
     */
    private function send(Model $record, array $data, Closure $pending): IssuedPublicLink
    {
        if (! in_array(HasSealedVersions::class, class_uses_recursive($record), true)) {
            throw new InvalidArgumentException(sprintf('%s does not use the HasSealedVersions trait.', $record::class));
        }

        // The language the version is sealed in; another language means another version.
        $locale = is_string($data['locale'] ?? null) && $data['locale'] !== '' ? $data['locale'] : App::getLocale();
        $user = auth()->user();
        $causer = $user instanceof Model ? $user : null;

        $version = app(Sealer::class)->seal($record, ApprovalContent::KIND, ApprovalContent::for($record, $locale), null, $causer, null);

        $issued = $pending($version, $this->purposeFor($record))->locale($locale)->issue();

        app(AuditLogger::class)->recordForVersion($version, 'sent', causer: $causer, properties: [
            'public_link_id' => $issued->link->getKey(),
            'locale' => $locale,
        ]);

        return $issued;
    }

    /**
     * A link the URL modal may show: one to a version of this very record.
     */
    private static function owns(Model $record, PublicLink $link): bool
    {
        $version = $link->linkable;

        return $version instanceof SealedVersion
            && $version->subject_type === $record->getMorphClass()
            && $version->subject_id === ModelKey::of($record);
    }
}
