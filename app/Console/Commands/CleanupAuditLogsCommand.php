<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class CleanupAuditLogsCommand extends Command
{
    protected $signature = 'l2forge:logs-clean
        {--days= : Удалить записи старше указанного количества дней}
        {--dry-run : Только показать количество записей без удаления}';

    protected $description = 'Remove expired L2Forge CMS audit log entries';

    public function handle(AuditLogger $auditLogger): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('cms.audit.retention_days', 90);

        if ($days < 1 || $days > 3650) {
            $this->error('Количество дней должно быть от 1 до 3650.');

            return self::FAILURE;
        }

        $threshold = now()->subDays($days);
        $query = AuditLog::query()->where('created_at', '<', $threshold);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Будет удалено записей: {$count}.");
            $this->line('Граница хранения: '.$threshold->format('d.m.Y H:i:s').'.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        if ($deleted > 0) {
            $auditLogger->system('system', 'audit.cleaned', details: [
                'retention_days' => $days,
                'deleted_count' => $deleted,
            ]);
        }

        $this->info("Удалено записей: {$deleted}.");

        return self::SUCCESS;
    }
}
