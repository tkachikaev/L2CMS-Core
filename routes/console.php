<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('l2forge:about', function () {
    $this->info('L2Forge CMS — open-source CMS for Lineage II servers.');
})->purpose('Show L2Forge CMS information');

Artisan::command('cms:about', function () {
    $this->warn('The cms:about alias is deprecated. Use l2forge:about.');
    $this->info('L2Forge CMS — open-source CMS for Lineage II servers.');
})->purpose('Deprecated alias for l2forge:about');
