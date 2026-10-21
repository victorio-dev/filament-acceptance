<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Approval;

use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Exceptions\ApprovalNotConfigured;

/**
 * The sealed content of a record sent for approval, self-describing so the public page needs no
 * configuration: the language it was sent in, the title, and each field with its label in that
 * language only.
 *
 *     {"kind": "approval", "locale": "hu", "title": "…",
 *      "fields": [{"key": "body", "label": "Szöveg", "type": "multiline", "value": "…"}]}
 *
 * @internal
 */
final class ApprovalContent
{
    public const string KIND = 'approval';

    /**
     * @return array{kind: string, locale: string, title: string, fields: list<array{key: string, label: string, type: string, value: mixed}>}
     *
     * @throws ApprovalNotConfigured
     */
    public static function for(Model $record, string $locale): array
    {
        $approvables = app(Approvables::class);
        $fields = $approvables->fieldsFor($record);

        if ($fields === []) {
            throw ApprovalNotConfigured::for($record);
        }

        return [
            'kind' => self::KIND,
            'locale' => $locale,
            'title' => $approvables->titleFor($record),
            'fields' => array_map(static fn (ApprovalField $field): array => [
                'key' => $field->attribute(),
                'label' => $field->labelFor($locale),
                'type' => $field->type(),
                'value' => $field->valueOf($record),
            ], $fields),
        ];
    }
}
