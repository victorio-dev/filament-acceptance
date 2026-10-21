<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Victorio\Acceptance\Approval\ApprovalField;
use Victorio\Acceptance\Concerns\HasSealedVersions;

/**
 * A record sent for approval without a handler: its approval fields come from the model.
 *
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property int|null $amount
 * @property bool $urgent
 * @property \Illuminate\Support\Carbon|null $due_on
 * @property float|null $price
 */
final class Document extends Model
{
    use HasSealedVersions;

    protected $table = 'fixture_documents';

    protected $guarded = [];

    /**
     * Tests may seal the price, a float, to see it refused.
     */
    public static bool $withPrice = false;

    /**
     * approvalConsentText() per language; none gives null (the translation).
     *
     * @var array<string, string>
     */
    public static array $consentTexts = [];

    protected function casts(): array
    {
        return ['urgent' => 'boolean', 'due_on' => 'date', 'amount' => 'integer', 'price' => 'float'];
    }

    /**
     * @return list<ApprovalField>
     */
    public function approvalFields(): array
    {
        return [
            ApprovalField::make('body')->multiline()->label(['en' => 'Text', 'hu' => 'Szöveg', 'de' => 'Text']),
            ApprovalField::make('amount')->integer()->label(['en' => 'Amount', 'hu' => 'Összeg']),
            ApprovalField::make('urgent')->boolean()->label(['en' => 'Urgent', 'hu' => 'Sürgős']),
            ApprovalField::make('due_on')->date()->label(['en' => 'Due on', 'hu' => 'Határidő']),
            ...(self::$withPrice ? [ApprovalField::make('price')->label(['en' => 'Price'])] : []),
        ];
    }

    public function approvalTitle(): string
    {
        return $this->title;
    }

    public function approvalConsentText(): ?string
    {
        return self::$consentTexts[App::getLocale()] ?? null;
    }
}
