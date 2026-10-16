<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic for the inbox "same person shown many times" problem.
 *
 * Reports two kinds of duplication, per workspace:
 *   1. Duplicate CONVERSATIONS — more than one conversation row sharing the same
 *      (workspace_id, contact_id, channel_account_id). firstOrCreate is supposed to
 *      prevent these; extras indicate a race (no unique index) or pre-fix data.
 *   2. Duplicate CONTACTS — more than one contact sharing the same channel identity
 *      (messenger_psid / instagram_psid). These came from the old per-message contact
 *      creation before PSID de-duplication was added.
 *
 * Makes NO changes. Use it to confirm the cause before any cleanup.
 *
 *   php artisan inbox:duplicate-report
 *   php artisan inbox:duplicate-report --workspace=2
 */
class InboxDuplicateReportCommand extends Command
{
    protected $signature = 'inbox:duplicate-report {--workspace= : Limit to one workspace id}';

    protected $description = 'Report duplicate inbox conversations and contacts (read-only)';

    public function handle(): int
    {
        $ws = $this->option('workspace');

        // --- 1. Duplicate conversations ----------------------------------------
        $convDupes = DB::table('conversations')
            ->select(
                'workspace_id',
                'contact_id',
                'channel_account_id',
                DB::raw('COUNT(*) as cnt'),
                DB::raw('MIN(id) as keep_id'),
                DB::raw('MAX(id) as newest_id')
            )
            ->when($ws, fn ($q) => $q->where('workspace_id', $ws))
            ->groupBy('workspace_id', 'contact_id', 'channel_account_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('cnt')
            ->get();

        $this->info('');
        $this->line('  <fg=cyan;options=bold>Duplicate CONVERSATIONS (same contact + same channel account)</>');
        if ($convDupes->isEmpty()) {
            $this->line('  none ✓');
        } else {
            $extra = 0;
            foreach ($convDupes as $row) {
                $extra += ($row->cnt - 1);
                $this->line(sprintf(
                    '  ws=%s contact=%s channel_account=%s → %d conversations (keep #%d, %d extra)',
                    $row->workspace_id, $row->contact_id, $row->channel_account_id ?? 'null',
                    $row->cnt, $row->keep_id, $row->cnt - 1
                ));
            }
            $this->warn("  → {$convDupes->count()} duplicated thread(s); {$extra} extra conversation row(s) could be merged away.");
        }

        // --- 2. Duplicate contacts by channel identity -------------------------
        foreach (['messenger_psid', 'instagram_psid'] as $key) {
            $identity = DB::connection()->getQueryGrammar()->wrap('custom_fields->'.$key);
            $contactDupes = DB::table('contacts')
                ->select(
                    'workspace_id',
                    DB::raw($identity.' as ident'),
                    DB::raw('COUNT(*) as cnt'),
                    DB::raw('MIN(id) as keep_id')
                )
                ->when($ws, fn ($q) => $q->where('workspace_id', $ws))
                ->whereNotNull('custom_fields->'.$key)
                ->groupBy('workspace_id', 'ident')
                ->havingRaw('COUNT(*) > 1')
                ->orderByDesc('cnt')
                ->get();

            $this->info('');
            $this->line("  <fg=cyan;options=bold>Duplicate CONTACTS by {$key}</>");
            if ($contactDupes->isEmpty()) {
                $this->line('  none ✓');
            } else {
                $extra = 0;
                foreach ($contactDupes as $row) {
                    $extra += ($row->cnt - 1);
                    $this->line(sprintf(
                        '  ws=%s %s=%s → %d contacts (keep #%d, %d extra)',
                        $row->workspace_id, $key, $row->ident, $row->cnt, $row->keep_id, $row->cnt - 1
                    ));
                }
                $this->warn("  → {$contactDupes->count()} duplicated person(s); {$extra} extra contact row(s).");
            }
        }

        $this->info('');
        $this->line('  <fg=gray>Read-only — nothing was modified.</>');
        $this->info('');

        return self::SUCCESS;
    }
}
