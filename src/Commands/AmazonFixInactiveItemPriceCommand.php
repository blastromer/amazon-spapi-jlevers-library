<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\PriceHelper;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonParentSku;
use Typhoeus\JleversSpapi\Models\MySql\AmazonShippingFeeHistory;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;


class AmazonFixInactiveItemPriceCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    =   'amz-spapi:inactive-item:fix-price
                                {--category=}
                                {--notifiction}
                                {--show}
                                ';
    protected $description  = 'This command will put all the listings from download flat file to MySQL database, these includes all the attributes related to product: See the example all, active, inactive';

    protected $listing;
    protected $amazonListing;
    protected $amazonParentSku;

    public $categories = ['all', 'active', 'inactive'];

    public function __construct(
        Listing $listing,
        AmazonPrice $amazonPrice,
        AmazonListing $amazonListing,
        AmazonParentSku $amazonParentSku,
        AmazonShippingFeeHistory $amazonShippingFeeHistory
    ) {
        parent::__construct();
        $this->listing          = $listing;
        $this->amazonPrice      = $amazonPrice;
        $this->amazonListing    = $amazonListing;
        $this->amazonParentSku  = $amazonParentSku;
        $this->amazonShippingFeeHistory  = $amazonShippingFeeHistory;
        $this->priceHelper = new PriceHelper($this->listing->app->getAppName());
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $appName = $this->listing->app->getAppName();
        // $flatRate = AppHelper::getFlatRate();
        $items = $this->amazonListing
            ->where('seller', $appName)
            ->where('status', 'Inactive')
            ->where('merchant_shipping_group', '!=', 'Free Economy')
            ->where('qty', '>', 0)
            ->get();
        $progressbar    = new ProgressBar(new Console(), $items->count());
        $progressbar->setFormat('Fixing Item Price %current%/%max% [%bar%] %percent:3s%% Elapsed: %elapsed:6s% Estimated: %remaining:6s% Memory: %memory:6s%');
        $progressbar->start();
        foreach ($items as $item) {
            $progressbar->advance();
            $sku = $item->getSku();
            if (!$shippedRate = $this->getHistoricalShipFee($sku)) {
                if (strpos($sku, 'po') !== false) {
                    $trimmedSku = str_replace('po', '', $sku);
                } else if (strpos($sku, 'kw') !== false) {
                    $trimmedSku = str_replace('kw', '', $sku);
                } else {
                    $trimmedSku = $sku;
                }
                $shippedRate = $this->getHistoricalShipFee($trimmedSku);
            }
            if (!$shippedRate) { // This will get the Flatrate as the last option for shipping fee
                $product = Product::where('productId', (int) $trimmedSku)->first();
                if (!$product) {
                    continue;
                }
                $totalFee = $this->priceHelper->getFlatRate($product);
            } else {
                $sumOfFee = $shippedRate->sum('fee');
                $totalQty = $shippedRate->count();
                $totalFee = (float) ($sumOfFee / $totalQty);
            }

            $priceRange = $this->getPricingRange($sku);
            if (!$priceRange) {
                continue;
            }
            $minPrice = $priceRange->getMinPrice();
            $ourPrice = (float) ($minPrice + $totalFee);
            $productType = $item->getProductType();
            $response = $this->patchOwnPrice($sku, $ourPrice, $productType);
        }
        $progressbar->finish();
    }

    public function getHistoricalShipFee($sku)
    {
        $shippingData = $this->amazonShippingFeeHistory
            ->where('sku', $sku)
            ->get();
        if ($shippingData->count() == 0) {
            return false;
        }
        return $shippingData;
    }

    public function getPricingRange($sku)
    {
        $price = $this->amazonPrice
            ->where('sku', $sku)
            ->first();
        if (!$price) {
            return false;
        }
        return $price;
    }

    public function patchOwnPrice($sku, $ourPrice, $productType)
    {
        $value = [
            [
                "our_price" => [
                    [
                        "schedule" => [
                            [
                                "value_with_tax" => $ourPrice,
                                "currency" => "USD"
                            ]
                        ]
                    ]
                ],
                "seller_fulfilled" => true
            ]
        ];
        $submit = $this->listing->patchItem($sku, 'purchasable_offer', $value, $productType);

        return $submit;
    }
}