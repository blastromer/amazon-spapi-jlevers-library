<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonParentSku;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class AmazonPutListingDBCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    =   'amz-spapi:put:listings
                                {--category=}
                                {--notifiction}
                                {--show}
                                ';
    protected $description  = 'This command will put all the listings from download flat file to MySQL database, these includes all the attributes related to product: See the example all, active, inactive';

    protected $listing;
    protected $amazonListing;
    protected $amazonParentSku;

    public $categories = ['all', 'active', 'inactive'];

    public function __construct(Listing $listing, AmazonListing $amazonListing, AmazonParentSku $amazonParentSku) {
        parent::__construct();
        $this->listing          = $listing;
        $this->amazonListing    = $amazonListing;
        $this->amazonParentSku  = $amazonParentSku;
    }

    public function handle()
    {
        $category           = $this->option('category');
        $appName            = $this->listing->app->getAppName();
        $defaultFileName    = "_amazon_list.csv";
        if (!in_array($category, $this->categories)) {
            $this->error("The option category is not accepting the value you provided, [{$category}] category is not valid.");
        }
        $dir                = $category . "/" . $appName;
        $defaultFileName    = $category . $defaultFileName;
        $this->info("Populating {$category} listings to MySql database...");
        if (!Storage::exists($dir)) {
            $this->info("The directory [{$idr}] is not existing. Creating new directory for listing...");
            if (!Storage::makeDirectory($dir)) {
                throw new Exception("Couldn't create new directory for [{$dir}]...");
            }
        }
        $defaultFileName    = $dir . "/" . $defaultFileName;
        $fileName           = $defaultFileName;
        $fullDir            = Storage::path($fileName);
        $lines              = file($fullDir, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $this->error('The file is empty.');
            return;
        }
        // $this->amazonListing->whereNotNull('id')->update(['is_skipped' => 1]);
        $headerLine = array_shift($lines); // Parse the header to get the field names
        $headers    = str_getcsv($headerLine, "\t");
        $arrayData  = [];
        $f          = 1;
        $s          = 1;
        $bar        = new ProgressBar(new Console(), count($lines));
        $bar->setFormat('Processing %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach ($lines as $line) {
            $rowData    = str_getcsv($line, "\t");
            $rowAssocs  = [];
            $parentSKU  = null;
            foreach ($headers as $index => $field) {
                $field = trim(preg_replace('/^\xEF\xBB\xBF/', '', $field)); // Remove BOM (Byte Order Mark)
                $rowAssocs[$field] = $rowData[$index] ?? null;
            }
            $sellerSKU  = $rowAssocs['seller-sku'] ?? null;
            $query = [
                'seller' => $appName,
                'sku' => $sellerSKU
            ];
            $data = [
                "is_skipped" => 0,
                "seller" => $appName,
                "sku" => $sellerSKU,
                "item_name" => $rowAssocs['item-name'] ?? null,
                "item_description" => $rowAssocs['item-description'] ?? null,
                "asin" => $rowAssocs['asin1'] ?? null,
                "qty" => $rowAssocs['quantity'] ?? 0,
                "listing_id" => $rowAssocs['listing-id'] ?? null,
                "price" => $rowAssocs['price'] ?? 0.00,
                "status" => $rowAssocs['status'] ?? null,
                "open_date" => $rowAssocs['open-date'] ?? null,
                "image_url" => $rowAssocs['image-url'] ?? null,
                "item_is_marketplace" => $rowAssocs['item-is-marketplace'] ?? null,
                "product_id_type" => $rowAssocs['product-id-type'] ?? null,
                "zshop_shipping_fee" => $rowAssocs['zshop-shipping-fee'] ?? null,
                "item_note" => $rowAssocs['item-note'] ?? null,
                "item_condition" => $rowAssocs['item-condition'] ?? null,
                "zshop_category1" => $rowAssocs['zshop-category1'] ?? null,
                "zshop_browse_path" => $rowAssocs['zshop-browse-path'] ?? null,
                "zshop_storefront_feature" => $rowAssocs['zshop-storefront-feature'] ?? null,
                "will_ship_internationally" => $rowAssocs['will-ship-internationally'] ?? null,
                "expedited_shipping" => $rowAssocs['expedited-shipping'] ?? null,
                "zshop_boldface" => $rowAssocs['item-name'] ?? null,
                "product_id" => $rowAssocs['product-id'] ?? null,
                "bid_for_featured_placement" => $rowAssocs['bid-for-featured-placement'] ?? null,
                "add_delete" => $rowAssocs['add-delete'] ?? null,
                "pending_quantity" => $rowAssocs['pending-quantity'] ?? 0,
                "fulfillment_channel" => $rowAssocs['fulfillment-channel'] ?? null,
                "merchant_shipping_group" => $rowAssocs['merchant-shipping-group'] ?? null
            ];
            if (!$this->amazonListing->updateOrCreate($query, $data)) {
                dump("SKU [{$sellerSKU}] failed to update...");
            }
            $parentSku = str_replace(['kw', 'po'], "", $sellerSKU);
            $parentQuery = [
                "seller" => $appName,
                "sku" => $parentSku,
            ];
            $parentData = [
                "seller" => $appName,
                "sku" => $parentSku,
            ];
            if (!$this->amazonParentSku->updateOrCreate($parentQuery, $parentData)) {
                dump("SKU [{$sellerSKU}] failed to update the parent data...");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info("[DONE...]");
        $this->info("Successfully Populates Amazon Listing Database");
    }
}
