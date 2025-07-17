<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class ManualPropagationCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    =   'amz-spapi-test:manual:listings
                                {--method=}
                                {--notifiction}
                                {--show}
                                ';
    protected $description  = 'This command will propagate all the items from downloaded file manually and cross match to Amazon listing database table, it includes the new item listing with new ASIN.';

    protected $cataglog;
    protected $listing;
    protected $amazonListing;
    protected $product;
    protected $amazonQualifying;

    public $methods = ['propagate'];

    public function __construct(Listing $listing, AmazonListing $amazonListing, Catalog $catalog, Product $product, AmazonQualifying $amazonQualifying) {
        parent::__construct();
        $this->catalog          = $catalog;
        $this->listing          = $listing;
        $this->amazonListing    = $amazonListing;
        $this->product          = $product;
        $this->amazonQualifying = $amazonQualifying;
    }

    public function handle()
    {
        $catalog            = $this->catalog->setSellerConfig(true);
        $method             = $this->option('method');
        $this->info("Propagating from manual listing...");
        $appName            = $this->listing->app->getAppName();
        $defaultFileName    = "default.csv";
        $sellerId           = $this->catalog->seller->config['amazon_merchant_id'];
        $marketplaceId      = $this->catalog->seller->config['marketplace_id'];
        $suggestedASIN          = $this->catalog->getSuggestedASIN(['UPC' => '4021534915185'], $marketplaceId, $sellerId);
        dd($suggestedASIN);
        if (!in_array($method, $this->methods)) {
            $this->error("The option method is not accepting the value you provided, [{$method}] method is not valid.");
        }
        if (!Storage::exists($method)) {
            if (!Storage::makeDirectory($method)) {
                $this->error("Failed to create new directory using this name [{$method}]");
            }
        }
        if (!Storage::exists("{$method}/{$appName}")) {
            $this->error("Folder not found, creating new directory [{$method}/{$appName}]...");
        }
        $fileName           = "{$method}/{$appName}/{$defaultFileName}";
        if (!Storage::exists($fileName)) {
            $this->error("File not found [{$fileName}]...");
        }
        $fullDir            = Storage::path($fileName);
        $lines              = file($fullDir, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $this->error('The file is empty.');
            return;
        }
        $headerLine = array_shift($lines); // Parse the header to get the field names
        $headers    = str_getcsv($headerLine, ",");
        $arrayData  = [];
        $f          = 1;
        $s          = 1;
        // $bar        = new ProgressBar(new Console(), count($lines));
        // $bar->setFormat('Propagating %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        // $bar->start();
        // dd(count($lines));
        $lastRow = $this->fetchLastRow();
        $lastSku = $lastRow ? $lastRow->getSku() : null;
        $startProcessing = false;
        foreach ($lines as $line) {
            $rowData    = str_getcsv($line, ",");
            $rowAssocs  = [];
            $parentSKU  = null;
            foreach ($headers as $index => $field) {
                $field              = trim(preg_replace('/^\xEF\xBB\xBF/', '', $field)); // Remove BOM (Byte Order Mark)
                $rowAssocs[$field]  = $rowData[$index] ?? null;
            }

            // listCatalogItems

            $listResponse = $this->catalog->getCatalogItemList($rowAssocs['UPC'], $rowAssocs['Description']);
            dd($listResponse);
            // Skip rows until we reach the last processed SKU
            if (!$startProcessing) {
                if ($rowAssocs['SKU'] === $lastSku) {
                    $startProcessing = true; // Start processing after this SKU is found
                }
                continue; // Skip this iteration until match
            }

            $sellerId       = $this->catalog->seller->config['amazon_merchant_id'];
            $marketplaceId  = $this->catalog->seller->config['marketplace_id'];
            $list           = $this->amazonListing->where('sku', $rowAssocs['SKU']);
            if (!$list->exists()) {
                $sellerSku = $rowAssocs['SKU'];
                if ($this->isExists($rowAssocs['SKU'])) {
                    $this->info("This sku [{$sellerSku}] is already existing..");
                    continue;
                }
                $productId              = str_replace(['po', 'kw'], "", $rowAssocs['SKU']);
                $product                = $this->product->where('productId', (int) $productId)->first();
                $brand                  = $product->getBrand();
                $mpn                    = $product->getMpn();
                $keywords               = $product->getKeywords();
                $rowAssocs['brand']     = $brand;
                $rowAssocs['mpn']       = $mpn;
                $rowAssocs['keywords']  = $keywords;
                $suggestedASIN          = $this->catalog->getSuggestedASIN($rowAssocs, $marketplaceId, $sellerId);
                if (isset($suggestedASIN['error'])) {
                    $logRequest = "Error message on sku[{$productId}]: " . $suggestedASIN['error']['message'];
                    $this->error($logRequest);
                    continue;
                }
                $items                  = json_decode(json_encode($suggestedASIN->getItems()), true);
                $resultArray            = [];
                foreach ($items as $item) {
                    $searchedItemName = "";

                    if (isset($item['summaries'][0]['itemName'])) {
                        $searchedItemName = $item['summaries'][0]['itemName'];
                        similar_text(strtolower($searchedItemName), strtolower($keywords), $percent);
                        if ($percent > 20) { // Adjust the threshold as needed
                            $resultArray[] = [
                                'items' => $item,
                                'result' => $percent
                            ];
                        } else {
                            continue; //This skips if less than 20 percent of threshold match...
                        }
                    }
                }
                if ($resultArray == []) {
                    continue;
                }
                // Find the max similarity result
                $maxResult = max(array_column($resultArray, 'result'));

                // Find the item with the highest result
                $highestItem = null;
                foreach ($resultArray as $entry) {
                    if ($entry['result'] == $maxResult) {
                        $highestItem = $entry['items'];
                        break;
                    }
                }

                if ($highestItem) {
                    $upc = isset($highestItem['attributes']['externally_assigned_product_identifier'][0]['value'])
                        ? $highestItem['attributes']['externally_assigned_product_identifier'][0]['value']
                        : "N/A";
                    $qualified = [
                        'seller'        => $appName,
                        'sku'           => $rowAssocs['SKU'],
                        'asin'          => ($highestItem['asin'] ?? ""),
                        'title'         => ($highestItem['summaries'][0]['itemName'] ?? ""),
                        'brand'         => ($highestItem['summaries'][0]['brand'] ?? ""),
                        'model_number'  => ($highestItem['summaries'][0]['modelNumber'] ?? ""),
                        'part_number'   => ($highestItem['summaries'][0]['partNumber'] ?? ""),
                        'upc'           => $upc
                    ];
                    $query = [
                        'seller'        => $appName,
                        'sku'           => $rowAssocs['SKU'],
                        'asin'          => ($highestItem['asin'] ?? "")
                    ];

                    if (!$this->setQualifyNewItem($query, $qualified)) {
                        $log = "UPC [{$upc}] was not found in MongoDB Product...";
                        $this->error($log);
                        \Log::error($log);
                        continue;
                    }
                    $this->info("Successfully put to qualifying items the UPC [{$upc}]...");
                } else {
                    echo "No matching item found.\n";
                }

            }
        }
    }

    public function setQualifyNewItem($query, $qualified)
    {
        if ($qualified['upc'] != "") {
            $product = Product::where('upc', (string) $qualified['upc'])
                ->exists();
            if (!$product) {
                return false;
            }

            if (!$this->amazonQualifying->firstOrCreate($query, $qualified)) {
                dump("Error on updating or creating this query [{$query}]");
            }
            return true;
        }

    }

    public function isExists($sku)
    {
        if ($this->amazonQualifying->where('sku', $sku)->exists()) {
            return true;
        }
        return false;
    }

    public function fetchLastRow()
    {
        $lastRow = $this->amazonQualifying->latest('id')->first();
        return $lastRow;
    }
}
