<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Exceptions\SealedVersionIsImmutable;

/**
 * The Eloquent builder of sealed versions: every mass write that could change or remove a sealed
 * row throws, so `SealedVersion::query()->update()`, `->delete()` and the same calls on a
 * subject's `sealedVersions()` relation fail loudly instead of rewriting evidence.
 *
 * Not covered, by design (the README says so): `->toBase()`, `DB::table()`, raw SQL and direct
 * database access. Those bypass Eloquent entirely; `SealedVersion::verify()` detects what they
 * change, and only database permissions can prevent it.
 *
 * The parameters stay untyped where the parent declares them untyped (rule D2 exception).
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 *
 * @internal
 */
final class SealedVersionBuilder extends Builder
{
    /**
     * @param  array<array-key, mixed>  $values
     */
    public function update(array $values): never
    {
        throw SealedVersionIsImmutable::make('update');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, string>|string  $uniqueBy
     * @param  array<array-key, mixed>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw SealedVersionIsImmutable::make('upsert');
    }

    /**
     * @param  string|null  $column
     */
    public function touch($column = null): never
    {
        throw SealedVersionIsImmutable::make('touch');
    }

    /**
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw SealedVersionIsImmutable::make('increment');
    }

    /**
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw SealedVersionIsImmutable::make('decrement');
    }

    /**
     * @param  array<string, float|int>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): never
    {
        throw SealedVersionIsImmutable::make('incrementEach');
    }

    /**
     * @param  array<string, float|int>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): never
    {
        throw SealedVersionIsImmutable::make('decrementEach');
    }

    public function delete(): never
    {
        throw EvidenceIsPermanent::make('delete');
    }

    public function forceDelete(): never
    {
        throw EvidenceIsPermanent::make('forceDelete');
    }

    public function truncate(): never
    {
        throw EvidenceIsPermanent::make('truncate');
    }
}
