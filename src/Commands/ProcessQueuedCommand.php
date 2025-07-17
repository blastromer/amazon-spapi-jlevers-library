<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProcessQueuedCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:queued:process';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(
        AmazonQualifying $amazonQualifying,
        Listing $listing
    ) {
        parent::__construct();
        $this->amazonQualifying = $amazonQualifying;
        $this->listing          = $listing;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $includedData   = 'issues';
        $appName = $this->listing->app->getAppName();
        $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $queuedItems = $this->amazonQualifying->where('seller', $appName)->where('is_queued', 1)->get();
        $s = 0;
        foreach ($queuedItems as $item) {
            // dd($item);
            $sku = $item->getSku();
            $responseIssues  = $this->listing->getItem($sellerId, $sku, $marketplaceId, $issueLocale = 'en_US', $includedData);
            if (isset($responseIssues['error'])) {
                $errMsg = "\t $sku \t this SKU did not pass the qualifying stage due to this error \t " . $responseIssues['error']['message'];
                $item->update(['is_queued' => 0, 'is_uploaded' => 0]);
                \Log::error($errMsg);
                $this->error($errMsg);
                continue;
            }
            if ($responseIssues->getIssues() != []) {
                $issues = $responseIssues->getIssues();
                foreach ($issues as $issue) {
                    if ($issue->severity == 'ERROR') {
                        $errMsg = "\t $sku \t this SKU did not pass the qualifying stage due to this error \t " . $issue;
                        $item->update(['is_queued' => 0, 'is_uploaded' => 0]);
                        \Log::error($errMsg);
                        $this->error($errMsg);
                        continue 2;
                    }
                }
            }
            if(!$item->update(['is_queued' => 0, 'is_uploaded' => 1])) {
                $this->error("Failed to update the queued to uploaded for SKU [{$item->getSku()}]...");
            }
            $this->info($s++ . " Successfully updated the queued to uploaded for SKU [{$item->getSku()}]...");
        }
    }
}