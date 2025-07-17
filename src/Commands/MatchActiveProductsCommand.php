<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Illuminate\Support\Facades\Storage;

class MatchActiveProductsCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = "amz-spapi-test:product-uploaded:matching";
    protected $description = "This command will map all the product from third party feed and check if it is qualified to list in amazon";

    public function __construct(AmazonListing $listing, AmazonQualifying $qualifying)
    {
        parent::__construct();
        $this->listing      = $listing;
        $this->qualifying   = $qualifying;
    }

    public function handle()
    {
        $this->info("Cross matching items if uploaded already...");
        $qualifyingLists = $this->qualifying->where();
        $s = 1;
        // \Log::info("\t SKU\t Name\t Description\t Brand\t ASIN\t UPC\t Product Type\t QTY\t Price\t Status\t");
        foreach ($qualifyingLists as $item) {
            // dump($item->getSku());
            // $cross = $this->listing->where('sku', $item->getSku());

            // if ($cross->exists()) {
                // \Log::info("\t{$item->getSku()}\t{$isExists->getItemName()}\t{$isExists->getItemDescription()}\t{$item->getBrand()}\t{$isExists->getAsin()}\t{$item->getUpc()}\t{$item->getProductType()}\t{$isExists->getQty()}\t{$isExists->getPrice()}\t{$isExists->getStatus()}");
                $this->info($s++ . ". The SKU [{$item->getSku()}] is MATCHED");
                // dd();
            // }
        }
    }
}