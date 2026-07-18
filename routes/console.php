<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('kaevcms:about', function () {
    $this->info('KaevCMS — open-source CMS for Lineage II servers.');
})->purpose('Show KaevCMS information');

Artisan::command('l2forge:about', function () {
    $this->warn('The l2forge:about alias is deprecated. Use kaevcms:about.');
    $this->info('KaevCMS — open-source CMS for Lineage II servers.');
})->purpose('Legacy alias for kaevcms:about');

Artisan::command('cms:about', function () {
    $this->warn('The cms:about alias is deprecated. Use kaevcms:about.');
    $this->info('KaevCMS — open-source CMS for Lineage II servers.');
})->purpose('Legacy alias for kaevcms:about');

Schedule::command('kaevcms:servers-monitor')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('kaevcms:logs-clean')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('queue:work database --queue=mail-probe,mail --stop-when-empty --max-time=50 --sleep=1 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->when(function (): bool {
        try {
            return Schema::hasTable('jobs')
                && DB::table('jobs')->whereIn('queue', ['mail-probe', 'mail'])->exists();
        } catch (Throwable) {
            return false;
        }
    });
