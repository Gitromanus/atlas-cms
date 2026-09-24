<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Планировщик AtlasCMS
|--------------------------------------------------------------------------
|
| Для работы на shared-хостинге (SpaceWeb) добавьте в cron панели хостинга:
|   * * * * * php /путь/к/project/artisan schedule:run >> /dev/null 2>&1
|
*/

// Обработка очередей (импорт каталога/предложений из 1С и др.)
Schedule::command('queue:work --once --tries=3 --timeout=120')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('atlas-queue')
    ->onFailure(function () {
        report(new RuntimeException('Очередь AtlasCMS не была обработана'));
    });
