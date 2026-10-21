<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use Victorio\Acceptance\Concerns\IsEvidence;
use Victorio\Acceptance\Exceptions\InternalPartUnreadable;
use Victorio\Acceptance\Exceptions\NotCanonicalizable;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Acceptance\Support\CanonicalJson;

/**
 * An immutable snapshot of a document's public content, in canonical JSON, with its SHA-256
 * hash. Created only through `HasSealedVersions::seal()`; never updated, never deleted through
 * Eloquent (IsEvidence; design.md a lists what that covers and what it cannot).
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
 * @property-read Decision|null $decision
 */
class SealedVersion extends Model
{
    use HasUlids;
    use IsEvidence;

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

    public function getTable(): string
    {
        return AcceptanceConfig::versionsTable();
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
     * The accept or decline of this version, if there is one (at most one, by a unique index).
     *
     * @return HasOne<Decision, $this>
     */
    public function decision(): HasOne
    {
        return $this->hasOne(AcceptanceConfig::decisionModel(), 'sealed_version_id');
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
}
