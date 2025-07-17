<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Services\Seller;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class ReconcileQualifyingItemsCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi-test:qualifying-items:reconcile';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;

    public function __construct(
        AmazonListing       $listing,
        AmazonQualifying    $qualifying,
        Seller              $seller
    ) {
        parent::__construct();
        $this->listing      = $listing;
        $this->qualifying   = $qualifying;
        $this->seller       = $seller;
    }

    public function handle()
    {
        $appName = $this->seller->app->getAppName();
        $this->info("Reconciling Amazon Qualifying Items...");
        $items   = $this->qualifying
            ->where('seller', $this->seller->app->getAppName())
            ->where("is_uploaded", 1)
            ->groupBy('sku')
            ->get();
        $i = 0;
        // dd($items->count());
        $progressbar        = new ProgressBar(new Console(), $items->count());
        $progressbar->setFormat('Processing Qualified items for KW %current%/%max% [%bar%] %percent:3s%% Elapsed: %elapsed:6s% Estimated: %remaining:6s% Memory: %memory:6s%');
        $progressbar->start();
        foreach ($items as $item) {
            $progressbar->advance();
            $sku = $item->getSku();
            // $listing = $this->listing
            //     ->where('sku', $sku)
            //     ->first();
            // if (!$listing) {
            //     // dump($listing);
            //     $item->update(['is_uploaded' => 0, 'is_skipped' => 0, 'is_queued' => 0]);
            //     dump($i++ . ". Not found [{$sku}] from Amazon All Listings, this will set to qualify again and will be included to the next listings...");
            // } else {
            //     // dd($listing);
            //     dump($i++ . ". Found this SKU [{$sku}]...");
            //     continue;
            // }

            $newQualifyingItem = $item->toArray();
            unset($newQualifyingItem['id'], $newQualifyingItem['created_at'], $newQualifyingItem['updated_at']);
            if (str_contains($newQualifyingItem['sku'], 'kw')) {
                continue;
            } else {
                $newQualifyingItem['sku'] = $newQualifyingItem['sku'] . "kw";
                $newQualifyingItem['branch_assigned'] = 11;

                $this->qualifying->firstOrCreate($newQualifyingItem);
            }
        }
        $progressbar->finish();
    }
}
