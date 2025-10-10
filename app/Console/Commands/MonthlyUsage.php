<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\UsageAnalyzerService;


class MonthlyUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show monthly usage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new UsageAnalyzerService();
        $summaries = $service->monthlyStats();
        $this->table(array_keys($summaries[0]), $summaries);
    }
}
