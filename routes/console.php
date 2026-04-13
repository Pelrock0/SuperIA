<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @psalm-suppress InvalidScope */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('accounts:delete-expired')->daily()->at('03:00');
Schedule::command('app:cleanup-collaborator-data')->hourly();
Schedule::command('ai:reset-daily-usage')->dailyAt('00:00')->timezone('Europe/Madrid');
Schedule::command('ai:cleanup-dismissed-suggestions')->dailyAt('03:30')->timezone('Europe/Madrid');
Schedule::command('ai:dispatch-weekly-summary')
    ->mondays()
    ->at('08:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping(60)
    ->onOneServer();
