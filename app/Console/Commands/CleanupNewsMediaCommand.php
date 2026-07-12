<?php

namespace App\Console\Commands;

use App\Services\News\NewsImageStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupNewsMediaCommand extends Command
{
    protected $signature = 'l2forge:news-media-clean
        {--hours=24 : Keep unreferenced files newer than this many hours}
        {--dry-run : Show what would be removed without deleting files}';

    protected $description = 'Remove old unreferenced cover and content images from news uploads';

    public function handle(NewsImageStorage $storage): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $newsRoot = $storage->rootPath().DIRECTORY_SEPARATOR.'news';

        if (! File::isDirectory($newsRoot)) {
            $this->info('News upload directory does not exist. Nothing to clean.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours)->getTimestamp();
        $removed = 0;
        $kept = 0;

        foreach (File::allFiles($newsRoot) as $file) {
            $relative = 'news/'.str_replace('\\', '/', $file->getRelativePathname());
            $normalized = $storage->normalizeNewsPath($relative);
            $isOldEnough = $file->getMTime() <= $cutoff;

            if ($normalized === null || ! $isOldEnough || $storage->isReferenced($normalized)) {
                $kept++;
                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '').'remove '.$normalized);

            if ($dryRun) {
                $removed++;
                continue;
            }

            if ($storage->deleteIfUnreferenced($normalized)) {
                $removed++;
            } else {
                $kept++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would remove' : 'Removed')." {$removed} file(s); kept {$kept} file(s).");

        return self::SUCCESS;
    }
}
