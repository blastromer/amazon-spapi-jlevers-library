<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Services\Feed;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;

class GetListingsOfferCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi:listing-offer:pull';
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
        $this->info('Checking price update...');
        $this->pricing->setSellerConfig(true);
        $offers = $this->pricing->getItemOffers();
        dump($offers);
    }
}