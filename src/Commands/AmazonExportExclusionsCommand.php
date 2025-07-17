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

class AmazonExportExclusionsCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi:export-report:sku-exclusions';
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

        $excludedSkus = $this->productExclusionList->all();

        foreach ($excludedSkus as $sku) {
            $productId  = $sku->getProductId();
            $title      = $sku->getProductTitle();
            $dateUntil  = $sku->getDateUntil();
            $isAmazon   = "false";
            $isPriceExclude = "false";
            $isQtyExclude = "false";
            $hasExpired = "false";

            if (is_null($title)) {
                $product = $this->product
                    ->where('productId', (int) $productId)
                    ->first();

                if ($product) {
                    $title = $product->getTitle();
                }
            }

            if ($sku->excludePrice()) {
                $isPriceExclude = "true";
            }

            if ($sku->excludeQty()) {
                $isQtyExclude = "true";
            }

            if ($sku->hasExpired()) {
                $hasExpired = "true";
            }

            $amazonItem = $this->amazonListing
                ->where('sku', 'like', $productId . '%')
                ->first();

            if ($amazonItem) {
                $isAmazon = (string) "true";
            }

            $log = "\t {$productId} \t {$isAmazon} \t {$title} \t {$isPriceExclude} \t {$isQtyExclude} \t {$hasExpired} \t {$dateUntil}";

            $this->info($log);
            \Log::info($log);
        }
    }
}