<?php

namespace Typhoeus\JleversSpapi\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonCompetitivePrice;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class CompareCompetitorsPriceReport extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi-test:compare:competitor-price';
    protected $description  = 'Catalog item collation command.';
    private $log_info = '';
    private $logs_data = [];
    protected $pricing;

    public function __construct(
        Pricing $pricing,
        AmazonListing $amazonListing,
        AmazonCompetitivePrice $amazonCompetitivePrice
    ) {
        parent::__construct();
        $this->pricing = $pricing;
        $this->amazonListing = $amazonListing;
        $this->amazonCompetitivePrice = $amazonCompetitivePrice;
    }

    public function handle()
    {
        $this->log_info = "Command:" . $this->signature . "\n\n";
        $this->pricing->setSellerConfig(true);
        $website = $this->pricing->app->getAppName();

        $listings = $this->amazonListing
            ->where('is_skipped', 0)
            ->where('seller', $website)
            ->get();

        $csvData = [];
        $csvData[] = ['SKU', 'ASIN', 'Own Price', "Competitor's Price", 'Variance'];

        foreach ($listings as $item) {
            $sku = $item->getSku();
            $asin = $item->getAsin();
            $ownPrice = $item->getPrice();
            $competitorsPrice = $this->amazonCompetitivePrice
                ->where('asin', $asin)
                ->first();

            if (!$competitorsPrice) {
                $this->error("This SKU [$sku] has no competitor price...");
                continue;
            }

            $landedPrice = $competitorsPrice->getLanded();
            if (!$landedPrice) {
                $this->error("This SKU [$sku] has no landed price");
            }

            $variance = $ownPrice - $landedPrice;

            $this->info("This SKU [$sku] with comparison from own price [$ownPrice] vs competitor's price [$landedPrice] - Variance: [$variance]");

            $csvData[] = [$sku, $asin, $ownPrice, $landedPrice, $variance];
        }

        // Save to CSV file
        $filename = 'competitor_price_report_' . Carbon::now()->format('Ymd_His') . '.csv';
        $filepath = "reports/$filename";

        $csvContent = "";
        foreach ($csvData as $row) {
            $csvContent .= implode(",", $row) . "\n";
        }

        Storage::disk('local')->put($filepath, $csvContent);
        $this->info("Report saved to storage at: " . storage_path("app/$filepath"));
    }
}
