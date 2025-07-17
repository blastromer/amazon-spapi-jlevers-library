<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Feed;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MongoDB\ProductAttribute;
use Typhoeus\JleversSpapi\Models\MongoDB\CatalogItems;
use Typhoeus\JleversSpapi\Models\MongoDB\CatalogItemAsin;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MySql\AmazonAsinProductType;
use Typhoeus\JleversSpapi\Models\MongoDB\Logs\ThrottlingListingsError;
use Typhoeus\JleversSpapi\Models\MongoDB\Logs\ProcessingListingError;
use Typhoeus\JleversSpapi\Http\Feed\Listing as JSONListing;
use Illuminate\Support\Facades\Storage;

class PutListingFeedCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:upload:json-listing-feed
        {--method=UPLOAD}
        {--subid=00000}
                            ';
    protected $description = 'This command will patch or change the specific field';

    protected $listing;
    protected $products;

    public $types               = ['SINGLE', 'BATCH'];
    public $categories          = ['NEW', 'EXISTING'];
    public $vendorInitial = [
        'po' => 'plumbersstock',
        'kw' => 'kentucky'
    ];
    public $defaultVendor       = ['po', 'kw'];
    public $primaryVendors      = ['plumbersstock', 'orgill', 'stockmarket'];
    public $secondaryVendors    = ['kentucky', 'orgill_mo'];
    public $buffer              = 0;
    public $phraseExcluded      = ['butt', 'bastard', 'cock'];

    public function __construct(
        Listing $listing,
        Product $products,
        AmazonListing $amazonListings,
        AmazonQualifying $amazonQualifying,
        ProductAttribute $productAttribute,
        Catalog $catalog,
        ThrottlingListingsError $throttlingListingsError,
        ProcessingListingError $processingListingError,
        AmazonAsinProductType $amazonAsinProductType,
        CatalogItems $catalogItems,
        CatalogItemAsin $catalogItemAsin,
        Feed $feed
    ) {
        parent::__construct();
        $this->catalog                  = $catalog;
        $this->listing                  = $listing;
        $this->products                 = $products;
        $this->amazonListings           = $amazonListings;
        $this->amazonQualifying         = $amazonQualifying;
        $this->productAttribute         = $productAttribute;
        $this->throttlingListingsError  = $throttlingListingsError;
        $this->processingListingError   = $processingListingError;
        $this->amazonAsinProductType    = $amazonAsinProductType;
        $this->catalogItems             = $catalogItems;
        $this->catalogItemAsin          = $catalogItemAsin;
        $this->feed                     = $feed;
    }

    public function handle()
    {
        $this->feed->setSellerConfig(true);
        $fileName = 'prostock_listings_item';
        $filePath = Storage::path($fileName . ".json");
        $method = $this->option('method');
        $subId = $this->option('subid');
        // dd($method);
        if (Storage::exists($fileName . ".json") && $method == "UPLOAD") {
            $result = $this->feed->uploadFeed($fileName);
        } else {
            $result = $this->feed->checkFeedID($subId);
        }
        dump($result);
    }
}