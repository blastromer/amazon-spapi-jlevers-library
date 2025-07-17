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

class GenerateReportChannelStatusCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    =   'amz-spapi-test:generate-report:channels';
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
        $this->listing->setSellerConfig(true);
        // $method             = $this->option('method');
        $this->info("Propagating from manual listing...");
        $appName            = $this->listing->app->getAppName();
        $defaultFileName    = "default.csv";
        // if (!in_array($method, $this->methods)) {
        //     $this->error("The option method is not accepting the value you provided, [{$method}] method is not valid.");
        // }
        // if (!Storage::exists($method)) {
        //     if (!Storage::makeDirectory($method)) {
        //         $this->error("Failed to create new directory using this name [{$method}]");
        //     }
        // }
        // if (!Storage::exists("{$method}/{$appName}")) {
        //     $this->error("Folder not found, creating new directory [{$method}/{$appName}]...");
        // }
        // $fileName           = "{$method}/{$appName}/{$defaultFileName}";
        $fileName           = "propagate/PO_Amazon/default.csv";
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
        $lastRow = $this->fetchLastRow();
        $lastSku = $lastRow ? $lastRow->getSku() : null;
        $startProcessing = false;
        $sellerId       = $this->catalog->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->catalog->seller->config['marketplace_id'];
        $i           = 1;
        $bar        = new ProgressBar(new Console(), count($lines));
        $bar->setFormat('Propagating %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        // dd($headers[0]);
        $i = 1;
        \Log::info("# \t $headers[0] \t $headers[1] \t $headers[2] \t $headers[3] \t Classified \t Uploaded \t Issues");
        foreach ($lines as $line) {
            // dd($line);
            $bar->advance();
            $rowData    = str_getcsv($line, ",");
            // $rowAssocs  = [];
            // $parentSKU  = null;
            // dd($rowData);
            $sku = null;
            foreach ($headers as $index => $field) {
                $field              = trim(preg_replace('/^\xEF\xBB\xBF/', '', $field)); // Remove BOM (Byte Order Mark)
                if ($rowData[$index] == "") {
                    continue;
                }
                $rowAssocs[$field]  = $rowData[$index] ?? null;
                if ($field == "SKU") {
                    $sku = $rowData[$index];
                }
            }
            if (!is_null($sku)) {
                $productID = $number = preg_replace('/[^0-9]/', '', $sku);
                $product = $this->product->where('productId', (int) $productID)->first();
                $classified = $product->channels['amazonPo'] ? (string) "True" : (string) "False";
                $upc = $rowAssocs['UPC'];
                $description = $rowAssocs['Description'];
                $itemCost = $rowAssocs['Item Cost'];
                if (str_contains($sku, 'po')) {
                    $amazListing = $this->amazonListing->whereIn('sku', [$productID, $productID."po"])->exists();
                } else {
                    $amazListing = $this->amazonListing->where('sku', $sku)->exists();
                }

                $uploaded = $amazListing ? (string) "True" : (string) "False";
                $issue = "None";
                if (!$amazListing) {
                    $issue = $this->getListingIssue($productID);
                }
                \Log::info($i++ .". \t $sku \t $upc \t $description \t $itemCost \t $classified \t $uploaded \t $issue");
            } else {
                continue;
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

            if (!$this->amazonQualifying->firstOrCreate($query, $qualified)) {
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

    public function getListingIssue($sku)
    {
        $includedData   = 'issues,summaries,attributes,offers,fulfillmentAvailability,procurement';
        $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $responseIssue  = $this->listing->getItem($sellerId, $sku, $marketplaceId, $issueLocale = 'en_US', $includedData);
        $message = null;
        if (isset($responseIssue["error"])) {
            return "NOT FOUND in the Marketplace";
        }
        foreach ($responseIssue->getIssues() as $issue) {
            if ($issue['severity'] == "ERROR") {
                $message = $issue['message'];
                break;
            }
        }
        return ($message);
    }
}
