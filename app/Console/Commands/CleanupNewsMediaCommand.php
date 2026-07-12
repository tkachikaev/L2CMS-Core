<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupNewsMediaCommand extends Command
{
    protected $signature = 'l2forge:news-media-clean
        {--hours=24 : Keep unreferenced files newer than this many hours}
        {--dry-run : Show what would be removed without deleting files}';

    protected $description = 'Remove old unreferenced images uploaded into news content';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $configuredRoot = trim((string) config('cms.news.uploads_path', ''));
        $uploadsRoot = rtrim($configuredRoot !== '' ? $configuredRoot : public_path('uploads'), '\\/');
        $contentRoot = $uploadsRoot.DIRECTORY_SEPARATOR.'news'.DIRECTORY_SEPARATOR.'content';

        if (! File::isDirectory($contentRoot)) {
            $this->info('News content upload directory does not exist. Nothing to clean.');

            return self::SUCCESS;
        }

        $referenced = [];

        News::withTrashed()
            ->select(['id', 'body'])
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$referenced): void {
                foreach ($items as $item) {
                    preg_match_all(
                        '~(?:^|["\'])/uploads/(news/content/\d{4}/\d{2}/[a-f0-9-]+\.(?:jpe?g|png|webp))(?:["\']|$)~i',
                        (string) $item->body,
                        $matches
                    );

                    foreach ($matches[1] ?? [] as $path) {
                        $referenced[strtolower($path)] = true;
                    }
                }
            });

        $cutoff = now()->subHours($hours)->getTimestamp();
        $removed = 0;
        $kept = 0;

        foreach (File::allFiles($contentRoot) as $file) {
            $relative = 'news/content/'.str_replace('\\', '/', $file->getRelativePathname());
            $isSupportedImage = preg_match('~\.(?:jpe?g|png|webp)$~i', $relative) === 1;
            $isReferenced = isset($referenced[strtolower($relative)]);
            $isOldEnough = $file->getMTime() <= $cutoff;

            if (! $isSupportedImage || $isReferenced || ! $isOldEnough) {
                $kept++;
                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '').'remove '.$relative);

            if (! $dryRun) {
                File::delete($file->getPathname());
            }

            $removed++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would remove' : 'Removed')." {$removed} file(s); kept {$kept} file(s).");

        return self::SUCCESS;
    }
}
