<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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
