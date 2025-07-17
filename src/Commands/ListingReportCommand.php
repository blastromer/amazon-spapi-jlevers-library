<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonParentSku;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MySql\AmazonAsinProductType;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class ListingReportCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    =   'amz-spapi-test:generate-report:listing-qualifying
                                ';
    protected $description  = 'This command will put all the listings from download flat file to MySQL database, these includes all the attributes related to product: See the example all, active, inactive';

    public function __construct(
        Listing             $listing,
        AmazonListing       $amazonListing,
        Catalog             $catalog,
        AmazonQualifying    $amazonQualifying,
        Product             $product,
        AmazonAsinProductType   $amazonAsinProductType
    ) {
        parent::__construct();
        $this->catalog          = $catalog;
        $this->listing          = $listing;
        $this->amazonListing    = $amazonListing;
        $this->amazonQualifying = $amazonQualifying;
        $this->product          = $product;
        $this->amazonAsinProductType          = $amazonAsinProductType;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $appName = $this->listing->app->getAppName();
        $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $includedData   = 'issues';
        $qualifying = $this->amazonQualifying
            ->where('seller', $appName)
            ->where('is_uploaded', 1)
            ->get();
        $i = 0;
        foreach ($qualifying as $item) {
            $sku = $item->getSku();
            $listing = $this->amazonListing
                ->where('seller', $appName)
                ->where('sku', $sku)
                ->first();
            if (!$listing) {
                // dump($i++);
                // continue;
                $responseIssue  = $this->listing->getItem($sellerId, $sku, $marketplaceId, $issueLocale = 'en_US', $includedData);
                $issues = $responseIssue->getIssues();
                foreach ($issues as $issue) {
                    $errorMessage = $issue->getMessage();
                    \Log::error("\t {$sku} \t {$errorMessage}");
                }
            }
        }
    }
}