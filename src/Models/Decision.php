<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Victorio\Acceptance\Concerns\IsEvidence;
use Victorio\Acceptance\Enums\DecisionKind;
use Victorio\Acceptance\Exceptions\EvidenceAlreadyStored;
use Victorio\Acceptance\Exceptions\EvidenceFileUnavailable;
use Victorio\Acceptance\Support\AcceptanceConfig;
use Victorio\Acceptance\Support\AuditLogger;

/**
 * The accept or decline of one sealed version, bound to the hash of exactly that content.
 * Recorded only through Decisions::record(); never changed or deleted through Eloquent, never
 * pruned (IsEvidence). Holds personal data (name, e-mail) the host is responsible for.
 *
 * Extension point: swap it through `filament-acceptance.models.decision` with a subclass, which is
 * why the class is not final (rule D3). Keep the parent's behaviour; the package relies on it.
 *
 * @property string $id
 * @property string $sealed_version_id
 * @property string|null $tenant_id
 * @property DecisionKind $decision
 * @property string|null $name
 * @property string|null $email
 * @property string|null $comment
 * @property string|null $reason
 * @property string|null $consent_text
 * @property string $locale
 * @property string $content_hash
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property string|null $public_link_id
 * @property int|null $public_link_access_id
 * @property string|null $evidence_disk
 * @property string|null $evidence_path
 * @property string|null $evidence_hash
 * @property Carbon $decided_at
 * @property-read SealedVersion|null $version
 */
class Decision extends Model
{
    use HasUlids;
    use IsEvidence;

    public $timestamps = false;

    /**
     * Nothing is mass assignable: the recorder fills every column explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [];

    public function getTable(): string
    {
        return AcceptanceConfig::decisionsTable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => DecisionKind::class,
            'public_link_access_id' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SealedVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AcceptanceConfig::versionModel(), 'sealed_version_id');
    }

    public function isAccepted(): bool
    {
        return $this->decision === DecisionKind::Accepted;
    }

    /**
     * Whether the decision still proves what it was about: the version verifies, the version's
     * hash is the one this decision was made on, and the evidence file (if any) is unchanged.
     */
    public function verify(): bool
    {
        $version = $this->version()->first();

        if ($version === null || ! $version->verify() || ! hash_equals($version->content_hash, $this->content_hash)) {
            return false;
        }

        return $this->evidence_path === null || $this->verifyEvidence();
    }

    /**
     * Stores the evidence file (e.g. the PDF of the accepted version) once, on the configured
     * disk, with its SHA-256. The package never deletes it.
     *
     * @throws EvidenceAlreadyStored
     */
    public function storeEvidence(string $contents, string $filename): void
    {
        if ($this->evidence_path !== null) {
            throw EvidenceAlreadyStored::for($this);
        }

        $hash = hash('sha256', $contents);
        $disk = AcceptanceConfig::evidenceDisk();
        // The hash prefix keeps a losing concurrent write from overwriting the winner's file.
        $path = AcceptanceConfig::evidencePath().'/'.$this->getKey().'/'.substr($hash, 0, 12).'-'.self::safeFilename($filename);

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException(sprintf('The evidence file of decision %s could not be written to disk "%s".', $this->getKey(), $disk));
        }

        $values = ['evidence_disk' => $disk, 'evidence_path' => $path, 'evidence_hash' => $hash];

        $this->getConnection()->transaction(function () use ($values, $hash): void {
            // The one write a decision ever gets, below Eloquent on purpose and only while empty.
            $written = $this->newQuery()->toBase()
                ->where($this->getKeyName(), $this->getKey())
                ->whereNull('evidence_path')
                ->update($values);

            if ($written !== 1) {
                throw EvidenceAlreadyStored::for($this);
            }

            $version = $this->version()->first();

            if ($version !== null) {
                app(AuditLogger::class)->recordForVersion($version, 'evidence_stored', properties: [
                    'decision_id' => $this->getKey(),
                    'evidence_hash' => $hash,
                ]);
            }
        });

        $this->setRawAttributes([...$this->getAttributes(), ...$values], sync: true);
    }

    /**
     * The evidence file's bytes, after checking them against the stored SHA-256.
     *
     * @throws EvidenceFileUnavailable
     */
    public function evidenceContents(): string
    {
        if ($this->evidence_path === null || $this->evidence_disk === null || $this->evidence_hash === null) {
            throw EvidenceFileUnavailable::notStored($this);
        }

        $contents = Storage::disk($this->evidence_disk)->get($this->evidence_path);

        if ($contents === null) {
            throw EvidenceFileUnavailable::missing($this);
        }

        if (! hash_equals($this->evidence_hash, hash('sha256', $contents))) {
            throw EvidenceFileUnavailable::tampered($this);
        }

        return $contents;
    }

    /**
     * Whether an evidence file is stored and unchanged.
     */
    public function verifyEvidence(): bool
    {
        try {
            $this->evidenceContents();
        } catch (EvidenceFileUnavailable) {
            return false;
        }

        return true;
    }

    /**
     * The evidence file's name as stored, without the hash prefix the package added
     * (e.g. "Q-2026-0042-v1.pdf"); null without a file.
     */
    public function evidenceFilename(): ?string
    {
        if ($this->evidence_path === null) {
            return null;
        }

        // The prefix is 12 hex characters and a dash; the name after it is never empty.
        return Str::after(basename($this->evidence_path), '-');
    }

    /**
     * The evidence file's MIME type as the disk detects it (from the contents, the extension as
     * fallback), e.g. "application/pdf"; null without a file, `application/octet-stream` when the
     * disk cannot tell. This is the real type: serve it through the package's downloads, which
     * send browser-executable types as `application/octet-stream`.
     */
    public function evidenceMimeType(): ?string
    {
        if ($this->evidence_path === null || $this->evidence_disk === null) {
            return null;
        }

        $type = Storage::disk($this->evidence_disk)->mimeType($this->evidence_path);

        return is_string($type) && $type !== '' ? $type : 'application/octet-stream';
    }

    private static function safeFilename(string $filename): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename)) ?? '';
        $name = trim(substr($name, -100), '.-');

        return $name === '' ? 'evidence' : $name;
    }
}
