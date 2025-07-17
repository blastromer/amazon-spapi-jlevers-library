<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmzSkipKeyword;
use Illuminate\Support\Facades\Storage;

class SyncMissingOfferPricingCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:sync:missing-offers';
    protected $description = 'This command will patch or change the specific field';

    protected $listing;

    public function __construct(
        Listing $listing,
        AmazonListing $amazonListing,
        AmazonPrice $amazonPrice,
        AmzSkipKeyword $amzSkipKeyword
    ) {
        parent::__construct();
        $this->listing          = $listing;
        $this->amazonListing    = $amazonListing;
        $this->amazonPrice      = $amazonPrice;
        $this->amzSkipKeyword      = $amzSkipKeyword;
    }

    public function handle()
    {
        $this->info('Matching Missing Offer products...');
        $prices = $this->amazonPrice
            ->where('seller', $this->listing->app->getAppName())
            ->where('ready_for_upload', 0)
            ->where('own_price', 0)
            ->where('min_price', '>', 0)
            // ->where('sku', '708127po')
            ->get();
        $skipKeywordArr = $this->amzSkipKeyword->all();
        $i = 0;
        // dd($prices->count());
        foreach ($prices as $price) {
            $sku = $price->getSku();
            $listing = $this->amazonListing
                ->where('sku', $sku)
                ->where('status', 'Incomplete')
                ->first();
            if (!$listing) {
                continue;
            }
            $itemName = $listing->getItemName();
            foreach ($skipKeywordArr as $word) {
                if (str_contains($itemName, $word->keyword)) {
                    continue 2;
                }
            }
            $price->update(['ready_for_upload' => 1]);
            dump($i++ . ". ". $sku);
        }
    }
}