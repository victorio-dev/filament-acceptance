<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Events\ModelsPruned;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Victorio\Acceptance\Concerns\IsEvidence;
use Victorio\Acceptance\Support\AcceptanceConfig;

/**
 * One line of a document's timeline: sealed, accepted, evidence stored, and whatever the consumer
 * records (sent, viewed, …). Written through AuditLog only; never changed or deleted through
 * Eloquent (IsEvidence). The only way out is pruning (rule E6), which spares the evidence chain.
 *
 * Extension point: swap it through `filament-acceptance.models.audit` with a subclass, which is
 * why the class is not final (rule D3). Keep the parent's behaviour; the package relies on it.
 *
 * @property int $id
 * @property string|null $tenant_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $sealed_version_id
 * @property string $event
 * @property string|null $causer_type
 * @property string|null $causer_id
 * @property string|null $ip_hash
 * @property array<string, mixed>|null $properties
 * @property Carbon $occurred_at
 */
class AuditEntry extends Model
{
    use IsEvidence;
    use MassPrunable;

    /**
     * The record an erasure leaves behind; never pruned.
     */
    public const string ERASURE = 'erasure';

    public $timestamps = false;

    /**
     * Nothing is mass assignable: AuditLog fills every column explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [];

    public function getTable(): string
    {
        return AcceptanceConfig::auditTable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'string',
            'properties' => 'array',
            'occurred_at' => 'datetime',
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
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Entries older than `audit.prune_after_days` that are not part of the evidence chain: without
     * a version, or of a version that is superseded and has no decision. Entries of a version that
     * has a decision, of a version that can still get one, and erasure records stay. Nothing is
     * prunable with `prune_after_days = null`.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = AcceptanceConfig::auditPruneAfterDays();
        $query = static::query();

        if ($days === null) {
            return $query->whereKey([]);
        }

        $versions = AcceptanceConfig::versionsTable();
        $decisions = AcceptanceConfig::decisionsTable();

        return $query
            ->where('occurred_at', '<', now()->subDays($days))
            ->where('event', '!=', self::ERASURE)
            ->where(static function (Builder $query) use ($versions, $decisions): void {
                $query->whereNull('sealed_version_id')
                    ->orWhereIn('sealed_version_id', static function (QueryBuilder $query) use ($versions, $decisions): void {
                        $query->select($versions.'.id')
                            ->from($versions)
                            ->whereNotNull($versions.'.superseded_at')
                            ->whereNotExists(static function (QueryBuilder $query) use ($versions, $decisions): void {
                                $query->select($decisions.'.id')
                                    ->from($decisions)
                                    ->whereColumn($decisions.'.sealed_version_id', $versions.'.id');
                            });
                    });
            });
    }

    /**
     * MassPrunable's own pruneAll() deletes through the Eloquent builder, which refuses deletes on
     * evidence; pruning is the one deletion an audit entry allows, so it goes below Eloquent here.
     */
    public function pruneAll(int $chunkSize = 1000): int
    {
        $total = 0;

        do {
            $total += $count = $this->prunable()->limit($chunkSize)->toBase()->delete();

            if ($count > 0) {
                event(new ModelsPruned(static::class, $total));
            }
        } while ($count > 0);

        return $total;
    }
}
