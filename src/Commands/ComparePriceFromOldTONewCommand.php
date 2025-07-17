<?php

namespace Typhoeus\JleversSpapi\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceLog;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class ComparePriceFromOldTONewCommand extends Command
{
    protected $signature    = 'amz-spapi-test:compare-price:before-after';
    protected $description  = 'Catalog item collation command.';

    public function __construct(
        Pricing $pricing,
        AmazonListing $amazonListing,
        AmazonPriceLog $amazonPriceLog
    ) {
        parent::__construct();
        $this->amazonListing    = $amazonListing;
        $this->pricing          = $pricing;
        $this->amazonPriceLog   = $amazonPriceLog;
    }

    public function handle()
    {
        $sellerName = $this->pricing->app->getAppName();

        $items = $this->amazonListing
            ->where('seller', $sellerName)
            ->where('is_skipped', 0)
            ->where('status', 'active')
            // ->where('sku', '615134kw')
            ->get();

            foreach ($items as $item) {
                $sku = $item->getSku();
                $minPriceArr = [];
                $message = "";

                $prices = $this->amazonPriceLog
                    ->where('sku', $sku)
                    ->orderBy('id', 'desc')
                    ->take(2)
                    ->get();
                foreach ($prices as $price) {
                    $minPriceArr[$sku][] = $price->getMinPrice();
                }

                if (!isset($minPriceArr[$sku]) || empty($minPriceArr[$sku]) || $minPriceArr[$sku] == []) {
                    continue;
                }

                if (!isset($minPriceArr[$sku][0], $minPriceArr[$sku][1])) {
                    continue;
                }

                $message = "[{$sku}] sku has the Latest Price of [". $minPriceArr[$sku][0] . "] and the Previous Price of [" . $minPriceArr[$sku][1] . "]";

                if (isset($minPriceArr[$sku][0], $minPriceArr[$sku][1])) {
                    if ($minPriceArr[$sku][0] == $minPriceArr[$sku][1]) {
                        // echo "Values are equal.";
                        continue;
                    } else {
                        // echo "Values are not equal.";
                        \Log::info($message);
                        $this->info($message);
                    }
                } else {
                    // echo "Values do not exist.";
                    continue;
                }
                // sleep(1);
                // dd();
            }

    }
}