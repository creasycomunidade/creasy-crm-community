<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Generates a GDPR data export ZIP for a workspace.
 *
 * The archive contains CSVs for every major data type and a manifest.json
 * with generation timestamp and record counts.
 */
class WorkspaceExportService
{
    /**
     * Build the export ZIP and return the relative storage path.
     * Stored under `exports/{workspaceId}/export_{timestamp}.zip`.
     */
    public function generate(User $user): string
    {
        $workspaceId = $user->current_workspace_id ?? $user->workspace_id;

        $tmpDir = sys_get_temp_dir().'/ws_export_'.$workspaceId.'_'.time();
        if (! mkdir($tmpDir, 0700, true) && ! is_dir($tmpDir)) {
            throw new \RuntimeException('Não foi possível criar o diretório temporário da exportação.');
        }

        $counts = [];

        try {

        // 1. Contacts
        $counts['contacts'] = $this->writeCsv($tmpDir.'/contacts.csv', function () use ($workspaceId) {
            return DB::table('contacts')
                ->where('workspace_id', $workspaceId)
                ->orderBy('id')
                ->lazyById(500);
        });

        // 2. Conversations + messages
        $counts['conversations'] = $this->writeCsv($tmpDir.'/conversations.csv', function () use ($workspaceId) {
            return DB::table('conversations')
                ->where('workspace_id', $workspaceId)
                ->orderBy('id')
                ->lazyById(500);
        });

        $counts['messages'] = $this->writeCsv($tmpDir.'/messages.csv', function () use ($workspaceId) {
            return DB::table('messages')
                ->whereIn('conversation_id', function ($q) use ($workspaceId) {
                    $q->select('id')->from('conversations')->where('workspace_id', $workspaceId);
                })
                ->orderBy('id')
                ->lazyById(500);
        });

        // 3. AI runs
        $counts['ai_runs'] = $this->writeCsv($tmpDir.'/ai_runs.csv', function () use ($workspaceId) {
            return DB::table('ai_runs')
                ->where('workspace_id', $workspaceId)
                ->orderBy('id')
                ->lazyById(500);
        });

        // 4. Campaigns + recipients
        $counts['campaigns'] = $this->writeCsv($tmpDir.'/campaigns.csv', function () use ($workspaceId) {
            return DB::table('campaigns')
                ->where('workspace_id', $workspaceId)
                ->orderBy('id')
                ->lazyById(500);
        });

        $counts['campaign_recipients'] = $this->writeCsv($tmpDir.'/campaign_recipients.csv', function () use ($workspaceId) {
            return DB::table('campaign_recipients')
                ->whereIn('campaign_id', function ($q) use ($workspaceId) {
                    $q->select('id')->from('campaigns')->where('workspace_id', $workspaceId);
                })
                ->orderBy('id')
                ->lazyById(500);
        });

        // 5. Automations + runs
        $counts['automations'] = $this->writeCsv($tmpDir.'/automations.csv', function () use ($workspaceId) {
            return DB::table('automations')
                ->where('workspace_id', $workspaceId)
                ->orderBy('id')
                ->lazyById(500);
        });

        $counts['automation_runs'] = $this->writeCsv($tmpDir.'/automation_runs.csv', function () use ($workspaceId) {
            return DB::table('automation_runs')
                ->whereIn('automation_id', function ($q) use ($workspaceId) {
                    $q->select('id')->from('automations')->where('workspace_id', $workspaceId);
                })
                ->orderBy('id')
                ->lazyById(500);
        });

        // 6. Audit log
        $counts['audit_log'] = $this->writeCsv($tmpDir.'/audit_log.csv', function () use ($workspaceId) {
            return DB::table('audit_logs')
                ->where('workspace_id', $workspaceId)
                ->orderBy('id')
                ->lazyById(500);
        });

        // 7. Social posts
        $counts['social_posts'] = $this->writeCsv($tmpDir.'/social_posts.csv', function () use ($workspaceId) {
            return DB::table('social_posts')
                ->where('workspace_id', $workspaceId)
                ->orderBy('id')
                ->lazyById(500);
        });

        // Manifest
        file_put_contents($tmpDir.'/manifest.json', json_encode([
            'generated_at' => now()->toIso8601String(),
            'workspace_id' => $workspaceId,
            'exported_by' => $user->email,
            'record_counts' => $counts,
        ], JSON_PRETTY_PRINT));

        // Zip everything
        $zipPath = $tmpDir.'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (glob($tmpDir.'/*') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // Store in local disk under exports/
        $storagePath = "exports/{$workspaceId}/export_".now()->format('Ymd_His').'.zip';
        Storage::put($storagePath, file_get_contents($zipPath));

            return $storagePath;
        } finally {
            // Personal-data exports must not survive failures in /tmp.
            foreach (glob($tmpDir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($tmpDir);
            @unlink($tmpDir.'.zip');
        }
    }

    /**
     * Write rows from a lazy query to a CSV file.
     * Returns the number of rows written (excluding the header).
     */
    private function writeCsv(string $path, callable $queryFactory): int
    {
        $handle = fopen($path, 'w');
        $count = 0;
        $headers = null;

        try {
            foreach ($queryFactory() as $row) {
                $rowArr = (array) $row;
                if ($headers === null) {
                    $headers = array_keys($rowArr);
                    fputcsv($handle, $headers);
                }
                fputcsv($handle, array_map($this->safeCsvCell(...), array_values($rowArr)));
                $count++;
            }
        } catch (\Exception) {
            // Table may not exist in all deployments — write empty CSV with note
            if ($headers === null) {
                fputcsv($handle, ['note']);
                fputcsv($handle, ['table_not_found']);
            }
        }

        if ($headers === null) {
            fputcsv($handle, ['(empty)']);
        }

        fclose($handle);

        return $count;
    }

    /**
     * Prevent spreadsheet formula injection when exported personal data is
     * opened in Excel/LibreOffice. Tabs and CR/LF can also hide a formula.
     */
    private function safeCsvCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}
