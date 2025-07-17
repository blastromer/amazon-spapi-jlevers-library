<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonParentSku;
use Typhoeus\JleversSpapi\Models\MySql\AmazonAsinProductType;
use Typhoeus\JleversSpapi\Models\MySql\ProductExclusionList;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;

class AmazonGenerateProductTypeListCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi:export-report:product-type';
    protected $description  = 'This command will put all the listings from download flat file to MySQL database, these includes all the attributes related to product: See the example all, active, inactive';

    public function __construct(
        Listing                 $listing,
        AmazonListing           $amazonListing,
        Catalog                 $catalog,
        productExclusionList    $productExclusionList,
        Product                 $product,
        AmazonAsinProductType   $amazonAsinProductType
    ) {
        parent::__construct();
        $this->catalog              = $catalog;
        $this->listing              = $listing;
        $this->amazonListing        = $amazonListing;
        $this->productExclusionList = $productExclusionList;
        $this->product              = $product;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $appName = $this->listing->app->getAppName();

        $items = $this->amazonListing
            ->where('seller', $appName)
            ->get();

        foreach ($items as $item) {
            $sku = $item->getSku();
            $asin = $item->getAsin();
            $title = $item->getItemName();
            $productType = $item->getProductType();
            if (empty($productType)) {
                continue;
            }
            $log = "\t {$sku} \t {$asin} \t {$title} \t {$productType} \t {$productType}";

            $this->info($log);
            \Log::info($log);
        }
    }
}