<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MongoDB\CatalogItemAsin;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonCompetitivePrice;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class PullProductPricesCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi-test:download:product-prices';
    protected $description  = 'Catalog item collation command.';

    public function __construct(
        Pricing $pricing,
        AmazonListing $amazonListing
    ) {
        parent::__construct();
        $this->pricing = $pricing;
        $this->amazonListing = $amazonListing;
    }

    public function handle()
    {
        $this->pricing->setSellerConfig(true);
        $website = $this->pricing->app->getAppName();
        // $prices = $this->pricing->getItemOffers('102410po');
        $listing = $this->amazonListing
            ->where('seller', $website)
            ->where('is_skipped', 0)
            // ->where('status', 'active')
            ->where('qty', '>', 0)
            ->get();

        dd($listing->count());
    }
}