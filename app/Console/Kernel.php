<?php

namespace App\Console;

use App\Services\DatasourceRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Prunes rows older than config('activitylog.delete_records_older_than_days')
        // (plan.md Fase 8) — nothing else in the app schedules or queues work yet.
        $schedule->command('activitylog:clean')->daily();

        // Fills each JOGO LAUT snapshot bucket as it opens (JogoLautSnapshot).
        // Buckets are aligned to the Unix epoch, as cron minutes are, so a
        // five-minute bucket opens exactly when this fires.
        $minutes = max(1, intdiv((int) config('jogolaut.cache.ttl', 300), 60));
        $schedule->command('cms:jogolaut-warm')
            ->cron("*/{$minutes} * * * *")
            ->withoutOverlapping()
            ->when(fn () => app(DatasourceRegistry::class)->isConfigured('jogolaut'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
