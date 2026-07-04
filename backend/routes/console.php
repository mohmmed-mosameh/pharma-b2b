<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\CloseExpiredRfqs;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// يغلق المناقصات المنتهية تلقائيًا كل دقيقة (يعتمد على schedule:work في الخلفية).
Schedule::command(CloseExpiredRfqs::class)
    ->everyMinute()
    ->withoutOverlapping();
