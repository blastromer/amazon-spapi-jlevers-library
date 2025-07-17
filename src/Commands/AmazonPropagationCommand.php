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

class AmazonPropagationCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    =   'amz-spapi:manual:listings
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
        // $defaultFileName    = "default.csv";
        $defaultFileName    = "cricut.csv";
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
        $headers    = str_getcsv($headerLine, "\t");
        $arrayData  = [];
        $f          = 1;
        $s          = 1;
        $lastRow = $this->fetchLastRow();
        $lastSku = $lastRow ? $lastRow->getSku() : null;
        $startProcessing = false;
        $sellerId       = $this->catalog->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->catalog->seller->config['marketplace_id'];
        $i           = 1;
        $bar        = new ProgressBar(new Console(), count($lines));
        $bar->setFormat('Propagating %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach ($lines as $line) {

            $bar->advance();
            $rowData    = str_getcsv($line, "\t");
            $rowAssocs  = [];
            $parentSKU  = null;
            $productMongo = $this->product->where('productId', (int) $rowData[0])->first();
            $rowData[2] = $productMongo->getUpc() ?? null;
            foreach ($headers as $index => $field) {
                $field              = trim(preg_replace('/^\xEF\xBB\xBF/', '', $field)); // Remove BOM (Byte Order Mark)
                $rowAssocs[$field]  = $rowData[$index] ?? null;
            }

            // if ($rowAssocs['UPC'] != '819812016341') {
            //     continue;
            // }


            // if ($rowAssocs['SKU'] != '914538') {
            //     continue;
            // }

            if ($rowAssocs['Amazon classified??'] == 'No') {
                continue;
            }

            $sku = $rowAssocs['SKU'];
            // if ($this->isExists($rowAssocs['SKU'])) {
            //     \Log::info("SKU [{$sku}] is already existing");
            //     continue 1;
            // }

            $list       = $this->amazonListing->where('seller', $appName)->where('sku', $sku);
            // dd($list->exists());
            if (!$list->exists()) {
                $upc = $rowAssocs['UPC'];
                $listResponse   = $this->catalog->getCatalogItemList($rowAssocs['UPC']);

                if (!method_exists($listResponse, "getPayload")) {
                    \Log::error("\t $sku \t this SKU has no data");
                    continue;
                }

                $payload        = $listResponse->getPayload();
                $items          = $payload->getItems();

                if (count($items) <= 0) {

                    $listResponse   = $this->catalog->searchAsinByItemName($rowAssocs['Description']);
                    $items          = $listResponse->getItems();
                    if (count($items) <= 0) {
                        $listResponse   = $this->catalog->searchAsinByIndentifier($rowAssocs['UPC']);
                        $items          = $listResponse->getItems();
                        if (count($items) <= 0) {
                            \Log::error("\t $upc \t this SKU failed to propagate");
                            continue;
                        }
                    }
                    $item = $items[0];
                    // dd($item->getAsin());
                }
                $item = $items[0];
                // dd($payload);
                // dd($items);
                if (isset($listResponse['error'])) {
                    $message = $listResponse['error']['message'];
                    if (preg_match('/\[\s*429\s*\]/', $message)) {
                        $errorMsgs = $message;
                        sleep(0.5);
                    } else {
                        $errorMsgs = $message;
                    }
                    \Log::error($errorMsgs);
                    dd($errorMsgs);
                } else {
                    // dd($rowAssocs);
                    if ($rowAssocs['UPC'] == "") {
                        \Log::error("\t $sku \t this is has no UPC");
                        continue;
                    }
                    foreach ($items as $item) {
                        // dd($item);
                        $identifiers    = $item->getIdentifiers() ?? false;

                        $itemAsin = method_exists($item, 'getAsin')
                            ? $item->getAsin()
                            : $identifiers->getMarketplaceAsin()->getAsin() ?? null;
                            // dd($itemAsin);
                        if (is_null($itemAsin)) {
                            \Log::error("\t $sku \t this SKU has no ASIN");
                            continue;
                        }

                        $attributes = method_exists($item, 'getAttributeSets') && is_array($item->getAttributeSets())
                            ? $item->getAttributeSets()[0]
                            : $item->getAttributes();

                        $qualified = [
                            'asin'          => $itemAsin,
                            'title'         => $rowAssocs['Description']
                        ];
                        // dump($qualified);
                        $query = [
                            'seller'        => $appName,
                            'sku'           => $rowAssocs['SKU']
                        ];
                        // $this->amazonQualifying->update($query, $qualified);
                        // dd($this->amazonQualifying->update($query, $qualified));
                        // dd($this->amazonQualifying->where($query)->first());
                        // if (!$this->amazonQualifying->update($query, $qualified)) {
                        //     \Log::error("\t $sku \t this SKU is not in our Listing");
                        //     continue;
                        // }
                        $updating = $this->amazonQualifying
                            ->where('seller', $appName)
                            ->where('sku', $rowAssocs['SKU'])
                            ->update([
                                'asin'  => $itemAsin,
                                'title' => $rowAssocs['Description']
                            ]);
                        // dd($updating);
                        continue;
                        $productTypeCatalog   = $this->catalog->getProductTypeByAsin($itemAsin) ?? null;


                        $asin           = $identifiers ? $identifiers->getMarketplaceAsin()->getAsin() : $itemAsin;
                        $title          = $identifiers ? $attributes->getTitle() : $rowAssocs['Description'];
                        $brand          = $identifiers ? $attributes->getBrand() : $attributes['brand'][0]->value ?? $rowAssocs['PRICE-LINE'];
                        // $manufacturere  = $identifiers ? $attributes->getManufacturer() : attributes['item_name'][0]->value;
                        $model          = $identifiers ? $attributes->getModel() : $attributes['model_number'][0]->value ?? $rowAssocs['productId'];
                        $packageQty     = $identifiers ? $attributes->getPackageQuantity() : 1;
                        $partNumber     = $identifiers ? $attributes->getPartNumber() : $attributes['part_number'][0]->value ?? $rowAssocs['productId'];
                        $productGroup   = $identifiers ? $attributes->getProductGroup() : $attributes['item_type_keyword'][0]->value ?? null;
                        $productType    = $identifiers ? $attributes->getProductTypeName() : $productTypeCatalog;
                        $publisher      = $identifiers ? $attributes->getPublisher() : null;
                        $studio         = $identifiers ? $attributes->getStudio() : null;

                        $upc = $rowAssocs['UPC'];
                        $qualified = [
                            'seller'        => $appName,
                            'sku'           => $sku,
                            'asin'          => $asin,
                            'title'         => $title,
                            'brand'         => $brand,
                            'model_number'  => $model,
                            'part_number'   => $partNumber,
                            'upc'           => $upc,
                            'product_group' => $productGroup,
                            'product_type'  => $productType,
                            'publisher'     => $publisher,
                            'studio'        => $studio,
                            'package_qty'   => $packageQty
                        ];
                        // dd($qualified);
                        $query = [
                            'seller'        => $appName,
                            'sku'           => $rowAssocs['SKU']
                        ];

                        if (!$this->setQualifyNewItem($query, $qualified)) {
                            $log = "UPC [{$upc}] was not found in MongoDB Product...";
                            // $this->error($log);
                            \Log::error($log);
                            continue;
                        }
                        \Log::info("The SKU [{$sku}] was successfully propagated...");
                        continue;
                    }
                }
            } else {
                $sku = $rowAssocs['SKU'];
                \Log::info("\t $sku \t SKU already listed");
            }
        }
        $bar->finish();
        $this->info("[DONE]");
        $this->info("Successfully propagated...");
    }

    public function setQualifyNewItem($query, $qualified)
    {
        if ($qualified['upc'] != "") {
            // $product = Product::where('upc', (string) $qualified['upc'])
            //     ->exists();
            // if (!$product) {
            //     return false;
            // }

            // if (!$this->amazonQualifying->firstOrCreate($query, $qualified)) {
            if (!$this->amazonQualifying->updateOrCreate($query, $qualified)) {
                return false;
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
