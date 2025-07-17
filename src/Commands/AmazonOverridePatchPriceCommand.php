<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;
use Typhoeus\JleversSpapi\Jobs\PriceRangePatchingJob;
use Typhoeus\JleversSpapi\Models\MongoDB\Jobs\JobMonitoring;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceLog;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceReport;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Carbon\Carbon;
use Exception;

class AmazonOverridePatchPriceCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:upload:override-price';

    protected $description = 'This command will patch or change the price range of our products';

    protected $listing;
    protected $amazonListing;

    public function __construct(Listing $listing, AmazonListing $amazonListing, Product $product, AmazonPriceLog $amazonPriceLog)
    {
        parent::__construct();
        $this->listing = $listing;
        $this->amazonListing = $amazonListing;
        $this->amazonPriceLog = $amazonPriceLog;
        $this->product = $product;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $products = $this->product
            ->where('brand', 'Blanco')
            ->where('pricing.mapMethod', 'Hard')
            ->where('productId', (int) 279470)
            ->get();
        $progressbar    = new ProgressBar(new Console(), $products->count());
        $progressbar->setFormat('Fixing Item Price %current%/%max% [%bar%] %percent:3s%% Elapsed: %elapsed:6s% Estimated: %remaining:6s% Memory: %memory:6s%');
        $progressbar->start();
        foreach ($products as $product) {
            $progressbar->advance();
            $skus = [$product->getProductId(), $product->getProductId() . "po", $product->getProductId() . "kw"];

            foreach ($skus as $sku) {
                $item = $this->amazonListing->where('sku', (string) $sku)->first();
                if (!$item) {
                    continue;
                }
                $priceHistory = $this->amazonPriceLog->where('sku', $sku)->orderByDesc('created_at')->first();
                if ($priceHistory) {
                    $shippingFee = $priceHistory->ship_fee;
                } else {
                    $shippingFee = 0;
                }
                $ourPrice = $product->getMapPrice() + $shippingFee;
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
                        "minimum_seller_allowed_price" => [
                            [
                                "schedule" => [
                                    [
                                        "value_with_tax" => $product->getMapPrice(),
                                        "currency" => "USD"
                                    ]
                                ]
                            ]
                        ],
                        "seller_fulfilled" => true
                    ]
                ];
                $productType = $item->product_type ?? null;
                $submit = $this->listing->patchItem($sku, 'purchasable_offer', $value, $productType);
                if (isset($submit['error'])) {
                    dump($submit['error']);
                }
            }
        }
        $progressbar->finish();
    }
}