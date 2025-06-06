<?php

namespace App\Console;

use App\Jobs\CheckPendingTransactions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Vérifier les transactions en attente toutes les 5 minutes
        $schedule->job(new CheckPendingTransactions)
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Nettoyer les anciennes transactions échouées (plus de 30 jours)
        $schedule->command('transactions:cleanup')
            ->daily()
            ->at('03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
