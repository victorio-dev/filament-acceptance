<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Commands;

use Illuminate\Console\Command;
use Victorio\Acceptance\Support\Eraser;

/**
 * php artisan filament-acceptance:erase "App\Models\Quote" 01J… --reason="GDPR request #123" [--purge]
 *
 * Works by morph type and id, so it also reaches the evidence of a subject that no longer exists.
 *
 * @internal
 */
final class EraseCommand extends Command
{
    protected $signature = 'filament-acceptance:erase
        {type : The subject\'s morph type (its class, or its morph map alias)}
        {id : The subject\'s key}
        {--reason= : Why; kept in the audit log}
        {--purge : Remove versions, decisions, evidence files and audit entries instead of anonymizing}
        {--force : Do not ask for confirmation}';

    protected $description = 'Deliberately erase the acceptance evidence of one subject (anonymize, or purge everything).';

    public function handle(Eraser $eraser): int
    {
        $type = self::text($this->argument('type'));
        $id = self::text($this->argument('id'));
        $reason = trim(self::text($this->option('reason')));
        $purge = (bool) $this->option('purge');

        if ($reason === '') {
            $this->error('Pass --reason: it is kept in the audit log.');

            return self::INVALID;
        }

        $what = $purge
            ? 'PURGE every sealed version, decision, evidence file and audit entry'
            : 'anonymize the decisions (name, e-mail, comment, reason, user agent, IP hash)';

        if (! $this->option('force') && ! $this->confirm(sprintf('This will %s of %s %s. It cannot be undone. Continue?', $what, $type, $id))) {
            $this->line('Nothing was changed.');

            return self::FAILURE;
        }

        $report = $purge ? $eraser->purge($type, $id, $reason, null) : $eraser->anonymize($type, $id, $reason, null);

        $this->info(sprintf(
            '%s: %d versions, %d decisions, %d evidence files, %d audit entries; recorded as audit entry %s.',
            $report->mode,
            $report->versions,
            $report->decisions,
            $report->evidenceFiles,
            $report->auditEntries,
            $report->record->getKey(),
        ));

        return self::SUCCESS;
    }

    /**
     * Console input arrives as `mixed`; only strings are meaningful here.
     */
    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
