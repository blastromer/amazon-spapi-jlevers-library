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

class PullListingPutQualifyingCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    =   'amz-spapi:pull-put:listing-qualifying
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
        $this->catalog->setSellerConfig(true);
        $this->listing->setSellerConfig(true);
        $appName        = $this->listing->app->getAppName();
        $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $includedData   = 'issues,summaries,attributes,offers,fulfillmentAvailability,procurement';
        $amzListings    = $this->amazonListing
            ->where('seller', $appName)
            // ->where('asin', 'B00CICVX7M')
            // ->where('product_type', null)
            // ->groupBy('asin')
            ->orderBy('id', 'ASC')
            ->get();
        // dd($amzListings->count());
        $progressbar    = new ProgressBar(new Console(), $amzListings->count());
        $progressbar->setFormat('Listing new items %current%/%max% [%bar%] %percent:3s%% Elapsed: %elapsed:6s% Estimated: %remaining:6s% Memory: %memory:6s%');
        $progressbar->start();
        $n = 0;
        $poCnt = 0;
        $kwCnt = 0;
        foreach ($amzListings as $item) {
            $progressbar->advance();
            $sku = $item->getSku();
            if (strpos($sku, 'po') !== false) {
                // dump("PO = " . $poCnt++);
                continue;
                $productId = str_replace('po', '', $sku);
                $existsListing = $this->amazonListing
                    ->whereIn('sku', [$productId, $productId . "kw"])
                    ->exists();
                if ($existsListing) {
                    \Log::error($productId . ". This SKU exists already");
                    continue;
                }
                $items = $this->catalog->getCatalogItemByASIN($item->getAsin());
                if (isset($items['error'])) {
                    \Log::error($item->getAsin() . ". This ASIN was not found in the Marketplace");
                    continue;
                }
                $itemAttr = $items->getAttributes();
                $itemSumm = $items->getSummaries();
                $product = $this->product->where('productId', (int) $productId)->first();
                $productTypeExists = $this->amazonAsinProductType->where('asin', $item->getAsin())->first();
                $packageQty = 1;
                $upc = !is_null($product->getUpc());
                $productType = !is_null($productTypeExists->getProductType()) ? $productTypeExists->getProductType() : $item->getProductType();
                $mpn = $product->getMpn();
                $brand = isset($itemAttr['brand']) ? $itemAttr['brand'][0]->value : $product->getBrand();
                $sku = $productId;
                $asin = $item->getAsin();
                $title = $item->getItemName();
                $qualifyItem = [
                    'seller' => $appName,
                    'sku' => $sku . "po",
                    'asin' => $asin,
                    'title' => $title,
                    'brand' => $brand,
                    'model_number' => $mpn,
                    'part_number' => $mpn,
                    'product_type' => $productType,
                    'package_qty' => 1,
                    'upc' => $upc
                ];
                // if (!$this->amazonQualifying->updateOrCreate($qualifyItem)) {
                //     dump($sku . '. This SKU is failed');
                // }
                dd($qualifyItem);
            } else if (strpos($sku, 'kw') !== false) {
                // echo "Contains 'po'";
                // dump("KW = " . $kwCnt++);
                continue;
            } else {
                $input = $sku;
                $productId = preg_replace("/[^0-9]/", "", $input);
                $newSku = $productId . "kw";
                $existsListing = $this->amazonListing
                    ->where('sku', $newSku)
                    ->exists();

                if ($existsListing) {
                    continue;
                }
                $items = $this->catalog->getCatalogItemByASIN($item->getAsin());
                if (isset($items['error'])) {
                    \Log::error($item->getAsin() . ". This ASIN was not found in the Marketplace");
                    $this->error($item->getAsin() . ". This ASIN was not found in the Marketplace");
                    continue;
                }
                // $product = $this->product->where('productId', (int) $productId)->first();
                // if ($product && $product->getInventory()['availability']['kentucky']['qty'] > 0 ) {
                //     dump( $n++ . ". $newSku Does not contain 'kw' or 'po' and isExists=[$existsListing]");
                // }
                $itemAttr = $items->getAttributes();
                $itemSumm = $items->getSummaries();
                $product = $this->product->where('productId', (int) $productId)->first();
                $productTypeExists = $this->amazonAsinProductType->where('asin', $item->getAsin())->first();
                $packageQty = 1;
                $upc = $product->getUpc() ?? "";
                if ($productTypeExists) {
                    $productType = !is_null($productTypeExists->getProductType()) ? $productTypeExists->getProductType() : $item->getProductType();
                } else {
                    $productType = $item->getProductType() ?? null;
                }

                $mpn = $product->getMpn();
                $brand = isset($itemAttr['brand']) ? $itemAttr['brand'][0]->value : $product->getBrand();
                $sku = $productId;
                $asin = $item->getAsin();
                $title = $item->getItemName();
                $qualifyItem = [
                    'seller' => $appName,
                    'sku' => $newSku,
                    'asin' => $asin,
                    'title' => $title,
                    'brand' => $brand,
                    'model_number' => $mpn,
                    'part_number' => $mpn,
                    'product_type' => $productType,
                    'package_qty' => 1,
                    'upc' => $upc
                ];
                $search = [
                    'seller' => $appName,
                    'sku' => $newSku,
                ];
                if (!$this->amazonQualifying->updateOrCreate($search, $qualifyItem)) {
                    dump($sku . '. This SKU is failed');
                }
                // dd($qualifyItem);
            }
            // if ($this->amazonQualifying->where(''))
            // $productType = $this->catalog->getProductTypeByAsin($item->getAsin());
            // $this->amazonListing
            //     ->where('seller', $appName)
            //     ->where('asin', $item->getAsin())
            //     ->update(['product_type' => $productType]);
        }
        $progressbar->finish();
    }
}