<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;
use Typhoeus\JleversSpapi\Jobs\PriceRangePatchingJob;
use Typhoeus\JleversSpapi\Models\MongoDB\Jobs\JobMonitoring;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceReport;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceLog;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyLog;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Carbon\Carbon;
use Exception;

class AmazonCleanOldRecordsCommand extends Command
{
    protected $description = 'This command is used to calculate and prepare price data to be patched';
    protected $signature  = 'amz-spapi:clean:logs {logType}
        ';

    public function handle()
	{
        $cutoff = Carbon::now()->subDays(30);
        $totalDeleted = 0;
        $logType = $this->argument('logType');

        do {
            if ($logType == 'price') {
                $deleted = AmazonPriceLog::where('created_at', '<', $cutoff)
                    ->limit(10000)
                    ->delete();
            } else if ($logType == 'qty') {
                $deleted = AmazonQtyLog::where('created_at', '<', $cutoff)
                    ->limit(10000)
                    ->delete();
            }

            $this->info("Total Deleted {$deleted}");

            $totalDeleted += $deleted;

            // Optional: short pause to reduce DB pressure
            usleep(100000); // 0.1 seconds
        } while ($deleted > 0);

        $this->info("$totalDeleted old records deleted.");
    }
}