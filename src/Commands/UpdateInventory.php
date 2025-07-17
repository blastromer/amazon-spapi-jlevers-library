<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Services\Feed;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;

class UpdateInventory extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:update:inventory';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;

    public function __construct(
        Listing $listing,
        Pricing $pricing,
        Feed $feed
    ) {
        parent::__construct();
        $this->listing = $listing;
        $this->pricing = $pricing;
        $this->feed = $feed;
    }

    public function handle()
    {
        // $arraySKUs = ['463097kw', '706504kw', '821168kw', '705324kw', '855971'];
        // $arraySKUs = ['949005'];
        // $this->info('Test Services...');
        // $configResult = $this->listing->setSellerConfig(true);
        // if ($configResult) {
        //     $this->info("\t [Services Pass]");
        // }
        // $this->info('Test Get Items...');
        // $getItemsData = $this->listing->getItemsBySKU($arraySKUs); // It uses an array of sku's (required parameter)
        // if ($getItemsData) {
        //     // dump($getItemsData);
        //     $this->info("\t [Get Items Pass]");
        // }
        // $this->info('Test Get Pricing...');
        // $configResultPrice = $this->pricing->setSellerConfig(true);
        // $getPricesdata = $this->pricing->getItemsPrice($arraySKUs);
        // if ($getPricesdata) {

        // }
        // this is working
        // $this->info('Test Create Feed...');
        // $sellerConfigForPrice = $this->feed->setSellerConfig(true);
        // $updatePrice = $this->feed->createFeed();

        // $this->feed->checkFeedID();

        $this->info('Test Update Inventory Feed...');
        $sellerConfig   = $this->feed->setSellerConfig(true);
        $updateInvetory = $this->feed->createFeed();

        dump($updateInvetory);
    }
}
