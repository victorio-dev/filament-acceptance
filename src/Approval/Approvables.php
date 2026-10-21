<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Approval;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Which fields of which models are sealed when sent for approval. Filled by
 * AcceptancePlugin::approvable() (or directly in a service provider); a model not registered here
 * uses its own approvalFields() and approvalTitle() (HasSealedVersions).
 *
 * @internal
 */
final class Approvables
{
    /**
     * @var array<class-string<Model>, array{title: string|(Closure(Model): mixed)|null, fields: list<ApprovalField>}>
     */
    private array $definitions = [];

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<ApprovalField>  $fields
     * @param  string|(Closure(TModel): mixed)|null  $title
     */
    public function register(string $model, string|Closure|null $title, array $fields): void
    {
        // Only ever called with a record of $model (titleFor() looks it up by class).
        /** @var string|(Closure(Model): mixed)|null $title */
        $this->definitions[$model] = ['title' => $title, 'fields' => $fields];
    }

    /**
     * @return list<ApprovalField>
     */
    public function fieldsFor(Model $record): array
    {
        if (isset($this->definitions[$record::class])) {
            return $this->definitions[$record::class]['fields'];
        }

        $fields = method_exists($record, 'approvalFields') ? $record->approvalFields() : [];

        return is_array($fields) ? array_values(array_filter($fields, static fn (mixed $field): bool => $field instanceof ApprovalField)) : [];
    }

    public function titleFor(Model $record): string
    {
        $title = $this->definitions[$record::class]['title'] ?? null;

        $value = match (true) {
            $title instanceof Closure => $title($record),
            is_string($title) => $record->getAttribute($title),
            method_exists($record, 'approvalTitle') => $record->approvalTitle(),
            default => null,
        };

        return is_scalar($value) && (string) $value !== '' ? (string) $value : class_basename($record).' '.$record->getKey();
    }
}
