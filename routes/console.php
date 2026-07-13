<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('l2forge:about', function () {
    $this->info('L2Forge CMS — open-source CMS for Lineage II servers.');
})->purpose('Show L2Forge CMS information');

Artisan::command('cms:about', function () {
    $this->warn('The cms:about alias is deprecated. Use l2forge:about.');
    $this->info('L2Forge CMS — open-source CMS for Lineage II servers.');
})->purpose('Deprecated alias for l2forge:about');

Schedule::command('l2forge:logs-clean')
    ->dailyAt('03:30')
    ->withoutOverlapping();
