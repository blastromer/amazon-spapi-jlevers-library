<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class AmazonGenerateImagesReportCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi-test:error-report:images';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;
    public $amazonQualifying;

    public function __construct(
        Product $product,
        Listing $listing,
        AmazonListing $amazonListing
    ) {
        parent::__construct();
        $this->product          = $product;
        $this->listing          = $listing;
        $this->amazonListing    = $amazonListing;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $appName        = $this->listing->app->getAppName();
        $issueLocale    = 'en_US';
        $includedData   = 'issues';

        // Create a file path (inside storage/app/)
        $csvFile = storage_path("app/amazon_image_issues_report.csv");

        // Open the CSV file for writing
        $handle = fopen($csvFile, 'w');

        // Add header row
        fputcsv($handle, ['SKU', 'Seller', 'ASIN', 'Issue Message']);

        $inactives = $this->amazonListing
            ->where('seller', $appName)
            ->where('status', 'Inactive')
            // ->where('sku', '64483') // You can remove this filter if needed
            ->get();

        $progressbar    = new ProgressBar(new Console(), $inactives->count());
        $progressbar->setFormat('Generating Listing Error %current%/%max% [%bar%] %percent:3s%% Elapsed: %elapsed:6s% Estimated: %remaining:6s% Memory: %memory:6s%');
        $progressbar->start();

        foreach ($inactives as $item) {
            $progressbar->advance();

            $sku            = $item->getSku() ?? null;
            $asin           = $item->getAsin() ?? null;
            $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
            $marketplaceId  = $this->listing->seller->config['marketplace_id'];
            $responseIssue  = $this->listing->getItem($sellerId, $sku, $marketplaceId, $issueLocale, $includedData);

            if (isset($responseIssue['issues'])) {
                $issues = $responseIssue['issues'];
                $firstRow = true;

                foreach ($issues as $issue) {
                    $message = $issue->getMessage();

                    if ($firstRow) {
                        fputcsv($handle, [$sku, $appName, $asin, $message]);
                        $firstRow = false;
                    } else {
                        fputcsv($handle, ["", "", "", $message]);
                    }
                }
            }
        }
        $progressbar->finish();
        fclose($handle);

        $this->info("Issues report saved to: $csvFile");
    }
}