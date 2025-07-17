<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Services\Feed;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;

class GetPriceCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi-test:pull-price:competitive';
    protected $description  = 'Test command for SP-API';

    protected $listing;
    protected $pricing;
    protected $feed;

    public function __construct(
        Listing $listing,
        Pricing $pricing,
        Feed $feed
    ) {
        parent::__construct();
        $this->listing  = $listing;
        $this->pricing  = $pricing;
        $this->feed     = $feed;
    }

    public function handle()
    {
        // $this->info('Checking price update...');

        // $oldPrice   = (float) 39.98;  // Old price to compare against
        // $attempt    = 0;

        // while (true) {
        //     $newPrice = $this->checkPrice();

        //     if ($newPrice != $oldPrice) {
        //         $this->info("\tPrice updated successfully to: {$newPrice}");
        //         break;  // Exit loop if price is updated
        //     } else {
        //         $attempt++;
        //         $this->info("\tPrice not updated yet. Attempt: {$attempt}, Current Price: {$newPrice}");
        //     }

        //     sleep(60); // Wait 1 minute before rechecking
        // }

        $this->pricing->setSellerConfig(true);
        $result = $this->pricing->getCompetitorsPrice();

        dd($result);
    }

    public function checkPrice()
    {
        $configResultPrice  = $this->pricing->setSellerConfig(true);
        $getPricesdata      = $this->pricing->getItemsPrice(['921626po']);

        return $getPricesdata[0]->getProduct()->getOffers()[0]->getRegularPrice()->getAmount();
    }
}
