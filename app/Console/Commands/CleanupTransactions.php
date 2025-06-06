<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;

class CleanupTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:cleanup {--days=30 : Number of days to keep failed transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old failed transactions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $count = Transaction::whereIn('status', ['failed', 'cancelled'])
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Deleted {$count} old transactions older than {$days} days.");

        return Command::SUCCESS;
    }
}
