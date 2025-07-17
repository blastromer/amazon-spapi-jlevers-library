<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmzSkipKeyword;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PullItemWithInvalidWordCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:invalid-title:pull';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(
        Listing        $listing,
        AmzSkipKeyword $amzSkipKeyword,
        AmazonListing  $amazonListing
    ) {
        parent::__construct();
        $this->listing = $listing;
        $this->amzSkipKeyword = $amzSkipKeyword;
        $this->amazonListing = $amazonListing;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $seller = $this->listing->app->getAppName();
        $skipWords = $this->amzSkipKeyword->pluck('keyword')->toArray();
        $listings = $this->amazonListing->where('seller', $seller)->get();
        // dump($skipWords);
        $found = false;
        $x = 1;
        foreach ($listings as $item) {
            foreach ($skipWords as $word) {
                if (stripos($item->getItemName(), $word) !== false) {
                    // if ($item->getQty() > 0) {
                    //     // public function patchItem($sku, $attr = 'fulfillment_availability', $value = [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 0]], $productType = null)
                    //     $response = $this->listing->patchItem($item->getSku(), 'fulfillment_availability', [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 0]]);
                    //     if (isset($response['error'])) {
                    //         dump($response['error']['message']);
                    //     } else {
                    //         $item->update(['is_skipped' => 1]);
                    //         $this->info($x++ . ". Found word {$word} in: {$item->getItemName()} WITH QTY of [{$item->getQty()}] from SKU [{$item->getSku()}]");
                    //         // dd();
                    //     }

                    // }

                    \Log::info("\t {$item->getSku()}\t {$item->getItemName()}\t {$item->getItemDescription()}\t {$item->getAsin()}\t {$item->getQty()}\t {$item->getPrice()}\t {$item->getStatus()}\t");
                    $found = true;
                    // $item->update(['is_skipped' => 1]);
                    // dd();
                }
            }
            // if ($hasSkipWord) {
            //     // dd($itemName); // This should now only show items that do NOT have skip words
            //     \Log::info("\t {$list->getSku()}\t {$itemName}\t {$list->getItemDescription()}\t {$list->getAsin()}\t ");
            //     dd();
            // }
        }
    }
}