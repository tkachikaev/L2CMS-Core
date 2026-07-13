<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class CleanupAuditLogsCommand extends Command
{
    protected $signature = 'l2forge:logs-clean
        {--days= : Delete entries older than the specified number of days}
        {--dry-run : Show the number of entries without deleting them}';

    protected $description = 'Remove expired L2Forge CMS audit log entries';

    public function handle(AuditLogger $auditLogger): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('cms.audit.retention_days', 90);

        if ($days < 1 || $days > 3650) {
            $this->error(__('The number of days must be between 1 and 3650.'));

            return self::FAILURE;
        }

        $threshold = now()->subDays($days);
        $query = AuditLog::query()->where('created_at', '<', $threshold);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info(__('Entries to delete: :count.', ['count' => $count]));
            $this->line(__('Retention threshold: :date.', ['date' => $threshold->format('d.m.Y H:i:s')]));

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        if ($deleted > 0) {
            $auditLogger->system('system', 'audit.cleaned', details: [
                'retention_days' => $days,
                'deleted_count' => $deleted,
            ]);
        }

        $this->info(__('Deleted entries: :count.', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
