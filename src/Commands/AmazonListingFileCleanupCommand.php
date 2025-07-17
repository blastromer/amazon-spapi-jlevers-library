<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Typhoeus\JleversSpapi\Services\Listing;

class AmazonListingFileCleanupCommand extends Command
{
    protected $signature = 'amazon-spapi:rotation:clean-listing-files';

    protected $description = 'Delete Amazon Listings files older than 7 days';

    public function __construct(
        Listing $listing
    ) {
		parent::__construct();
        $this->listing = $listing;
	}

    public function handle()
    {
        $appName = $this->listing->app->getAppName();
        $directory = storage_path("app/all/{$appName}");
        $files = glob($directory . '/*.txt');

        // Get the start of the current week (Monday)
        $startOfWeek = Carbon::now()->startOfWeek(); // Monday

        foreach ($files as $file) {
            $basename = basename($file);

            if (preg_match('/(\d{4}-\d{2}-\d{2})_\d{2}-\d{2}-\d{2}/', $basename, $matches)) {
                $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1]);

                if ($fileDate->lt($startOfWeek)) {
                    unlink($file);
                    $this->info("Deleted: $basename");
                }
            } else {
                $this->warn("Filename does not match expected pattern: $basename");
            }
        }

        $this->info('Cleanup complete: all files before this week deleted.');
        return 0;
    }
}