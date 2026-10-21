<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Approval;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One field of a record that goes into its sealed content when it is sent for approval, with
 * its label per language:
 *
 *     ApprovalField::make('body')->multiline()->label(['en' => 'Text', 'hu' => 'Szöveg']);
 *     ApprovalField::make('price_eur')->integer()->label('my-app::approval.price');   // a translation key
 *
 * Only the label of the language chosen when sending is sealed (English as fallback).
 */
final class ApprovalField
{
    public const string TEXT = 'text';

    public const string MULTILINE = 'multiline';

    public const string INTEGER = 'integer';

    public const string BOOLEAN = 'boolean';

    public const string DATE = 'date';

    private string $type = self::TEXT;

    /**
     * @var array<string, string>|string|null
     */
    private array|string|null $label = null;

    private function __construct(
        private readonly string $attribute,
    ) {}

    public static function make(string $attribute): self
    {
        return new self($attribute);
    }

    /**
     * Labels per locale (`['en' => …, 'hu' => …]`), or a translation key.
     *
     * @param  array<string, string>|string  $label
     */
    public function label(array|string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function multiline(): self
    {
        $this->type = self::MULTILINE;

        return $this;
    }

    public function integer(): self
    {
        $this->type = self::INTEGER;

        return $this;
    }

    public function boolean(): self
    {
        $this->type = self::BOOLEAN;

        return $this;
    }

    public function date(): self
    {
        $this->type = self::DATE;

        return $this;
    }

    public function attribute(): string
    {
        return $this->attribute;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * The label in that language: the array entry, else the English one; a translation key in
     * that language; without a label, the attribute name.
     */
    public function labelFor(string $locale): string
    {
        if (is_array($this->label)) {
            return $this->label[$locale] ?? $this->label['en'] ?? $this->attribute;
        }

        if (is_string($this->label)) {
            $text = __($this->label, [], $locale);

            return is_string($text) ? $text : $this->label;
        }

        return $this->attribute;
    }

    /**
     * The record's value in the field's type. A float stays a float, so sealing refuses it
     * (sealed content holds no floats).
     */
    public function valueOf(Model $record): mixed
    {
        $value = $record->getAttribute($this->attribute);

        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            self::INTEGER => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) ? (int) $value : $value,
            self::BOOLEAN => (bool) $value,
            self::DATE => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (is_scalar($value) ? (string) $value : $value),
            default => is_scalar($value) && ! is_float($value) ? (string) $value : $value,
        };
    }
}
