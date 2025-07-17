<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Illuminate\Support\Facades\Storage;

class SyncListingCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:sync:all-listing:products';
    protected $description = 'This command will patch or change the specific field';

    protected $listing;
    public $vendorInitial = [
        'po' => 'plumbersstock',
        'kw' => 'kentucky'
    ];
    public $defaultVendor       = ['po', 'kw'];
    public $primaryVendors      = ['plumbersstock'];
    public $secondaryVendors    = ['kentucky'];
    public $buffer              = 0;

    public function __construct(Listing $listing, Product $products)
    {
        parent::__construct();
        $this->listing = $listing;
        $this->products = $products;
    }

    public function handle()
    {
        $this->info('Matching Inactive products...');
        $this->listing->setSellerConfig(true);
        $this->buffer = $this->listing->app->sellerConfig->getBuffer();
        // $fileName = "all/All+Listings+Report+02-05-2025.txt"; // Cricut
        // $fileName = "all/" . $this->listing->app->getAppName() . "/All+Listings+Report+02-10-2025.txt"; // PO
        $fileName = "all/" . $this->listing->app->getAppName() . "/all_amazon_list.csv"; // PO
        if (!Storage::exists($fileName)) {
            $this->error("File {$fileName} not found.");
            return;
        }
        $fullDir    = Storage::path($fileName);
        $lines      = file($fullDir, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $this->error('The file is empty.');
            return;
        }
        $headerLine = array_shift($lines); // Parse the header to get the field names
        $headers    = str_getcsv($headerLine, "\t");
        $arrayData  = [];
        $f          = 1;
        $s          = 1;
        foreach ($lines as $line) {
            $rowData    = str_getcsv($line, "\t");
            $rowAssocs  = [];
            $parentSKU  = null;
            foreach ($headers as $index => $field) {
                $field = trim(preg_replace('/^\xEF\xBB\xBF/', '', $field)); // Remove BOM (Byte Order Mark)
                $rowAssocs[$field] = $rowData[$index] ?? null;
            }
            $sellerSKU  = $rowAssocs['seller-sku'] ?? null;
            $vendorName = $this->vendorInitial['po'];
            if (preg_match('/[a-zA-Z]+/', $sellerSKU, $matches)) { // this will get the vendor initial like kw for kentucky branch 11 or po for branch 8
                if (!in_array($matches[0], $this->defaultVendor)) {
                    $vendorName     = $this->vendorInitial['po'];
                } else {
                    $vendorName     = $this->vendorInitial[$matches[0]];
                }
                $parentSKU  = (int) trim($sellerSKU);
            } else {
                $parentSKU  = (int) trim($sellerSKU);
            }
            $product    = $this->products->where('productId', (int) trim($parentSKU))->first();
            if (!$product) {
                dump($sellerSKU. 'error');
                continue;
            }
            $fromDBQty  = (int) $product->getVendor($vendorName)['qty'] ?? 0;
            if ($fromDBQty <= $this->buffer && !in_array($vendorName, $this->secondaryVendors)) {
                $filtered = array_filter($product->getAvailability(), function ($vendor) { // Filter vendors with more than 2 qty
                    return $vendor['qty'] > $this->buffer && $vendor['cost'] > 0;
                });
                if ($filtered == []) {
                    $fromDBQty  = (int) $fromDBQty ?? 0;
                } else {
                    $lowestCostVendor = array_reduce(array_keys($filtered), function ($carry, $vendor) use ($filtered) {
                        if ($carry === null || $filtered[$vendor]['cost'] < $filtered[$carry]['cost']) {
                            return $vendor;
                        }
                        return $carry;
                    }, null);
                    $fromDBQty  = (int) $product->getVendor($lowestCostVendor)['qty'] ?? 0;
                }
            }
            $fromAmazonQty  = (int) $rowAssocs['quantity'] ?? 0;
            if ($fromDBQty <= 0) {
                $fromDBQty = 0;
            }
            if (isset($rowAssocs['item-name']) && $rowAssocs['item-name'] == "") {
                continue;
            }
            if ($fromDBQty < $fromAmazonQty && $rowAssocs['status'] == 'Active') {
                $logReport      = "\t" . $f++ . ".\tsku[{$sellerSKU}]\thas\t[{$fromAmazonQty}]QTY\tfrom\t".$this->listing->app->getAppName()."\tbut is not synced with EclipseDB, which shows\t[{$fromDBQty}]QTY";
                $fullfilledQty  = (int) ($fromDBQty - $this->buffer);
                if ($fromDBQty <= $this->buffer) {
                    $fullfilledQty = (int) 0;
                }
                // if ($this->listing->patchItem($sellerSKU, $attr = 'fulfillment_availability', $value = [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => $fullfilledQty]])) {
                    $this->error($logReport . ' [SYNC DONE]');
                    // \Log::info($logReport . ' [SYNC DONE]');
                    // \Log::info($logReport);
                // }
                // sleep(0.5);
            } else {
                $rebuff = $fromDBQty - $this->buffer;
                $logReport      = $s++ . ". SKU[{$sellerSKU}] has [{$fromAmazonQty}]QTY from ".$this->listing->app->getAppName()." and synced with EclipseDB [{($rebuff)}]QTY";
                // $this->info($logReport);
            }
        }
    }
}
