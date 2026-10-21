<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Models;

use Filament\Facades\Filament;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use Victorio\Acceptance\Builders\SealedVersionBuilder;
use Victorio\Acceptance\Exceptions\EvidenceIsPermanent;
use Victorio\Acceptance\Exceptions\InternalPartUnreadable;
use Victorio\Acceptance\Exceptions\NotCanonicalizable;
use Victorio\Acceptance\Exceptions\SealedVersionIsImmutable;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Acceptance\Support\CanonicalJson;
use Victorio\Acceptance\Support\ModelKey;

/**
 * An immutable snapshot of a document's public content, in canonical JSON, with its SHA-256
 * hash. Created only through `HasSealedVersions::seal()`; never updated, never deleted through
 * Eloquent (see SealedVersionBuilder for what that covers and what it cannot).
 *
 * Extension point: swap it through `filament-acceptance.models.version` with a subclass, which is
 * why the class is not final (rule D3). Keep the parent's behaviour; the package relies on it.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $kind
 * @property int $number
 * @property string $content
 * @property string $content_hash
 * @property string|null $internal
 * @property string|null $sealed_by_type
 * @property string|null $sealed_by_id
 * @property Carbon $sealed_at
 * @property Carbon|null $superseded_at
 * @property-read Model|null $subject
 */
class SealedVersion extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * Nothing is mass assignable: Sealer fills every column explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * The internal part is ciphertext for the admin side only; it never leaves the model in
     * arrays or JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'internal',
    ];

    protected static function booted(): void
    {
        // The builder refuses mass writes; these cover the model paths that fire events.
        static::updating(static function (): never {
            throw SealedVersionIsImmutable::make('save');
        });

        static::deleting(static function (): never {
            throw EvidenceIsPermanent::make('delete');
        });
    }

    public function getTable(): string
    {
        return AcceptanceConfig::versionsTable();
    }

    /**
     * Refused outright: with nothing fillable, update() would otherwise discard the attributes
     * silently and report success.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw SealedVersionIsImmutable::make('update');
    }

    /**
     * @param  QueryBuilder  $query
     * @return SealedVersionBuilder<static>
     */
    public function newEloquentBuilder($query): SealedVersionBuilder
    {
        /** @var SealedVersionBuilder<static> $builder */
        $builder = new SealedVersionBuilder($query);

        return $builder;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'string',
            'number' => 'integer',
            'sealed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sealedBy(): MorphTo
    {
        return $this->morphTo('sealed_by');
    }

    /**
     * The public content, decoded.
     *
     * @return array<array-key, mixed>
     */
    public function content(): array
    {
        /** @var array<array-key, mixed> $content */
        $content = json_decode($this->content, true, flags: JSON_THROW_ON_ERROR);

        return $content;
    }

    /**
     * The internal part, decrypted — admin side only, never for a public view.
     *
     * @return array<array-key, mixed>|null
     *
     * @throws InternalPartUnreadable when the app key changed without `app.previous_keys`
     */
    public function internal(): ?array
    {
        if ($this->internal === null) {
            return null;
        }

        try {
            /** @var array<array-key, mixed> $internal */
            $internal = json_decode(Crypt::decryptString($this->internal), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException $exception) {
            throw InternalPartUnreadable::because($exception);
        }

        return $internal;
    }

    /**
     * Whether the stored content is still exactly what was sealed: its SHA-256 equals the stored
     * hash, and it is in canonical form (so it cannot be a re-encoded copy with a fixed-up hash).
     */
    public function verify(): bool
    {
        $content = $this->content;

        if (! hash_equals($this->content_hash, hash('sha256', $content))) {
            return false;
        }

        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($decoded)) {
            return false;
        }

        try {
            return CanonicalJson::encode($decoded) === $content;
        } catch (NotCanonicalizable) {
            return false;
        }
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    /**
     * With Filament tenancy on and a current tenant, only that tenant's versions (rule D9).
     *
     * @param  Builder<static>  $query
     */
    public function scopeForCurrentTenant(Builder $query): void
    {
        $tenant = AcceptanceConfig::tenancyEnabled() ? Filament::getTenant() : null;

        if ($tenant === null) {
            return;
        }

        $query->where($query->qualifyColumn(AcceptanceConfig::tenantColumn()), ModelKey::of($tenant));
    }
}
