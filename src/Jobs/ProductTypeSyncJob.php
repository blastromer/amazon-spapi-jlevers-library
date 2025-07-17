<?php

namespace Typhoeus\JleversSpapi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use SellingPartnerApi\Api\ListingsApi;
use SellingPartnerApi\Configuration;
use SellingPartnerApi\Endpoint;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Models\MongoDB\Jobs\JobMonitoring;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceReport;
use Carbon\Carbon;
use Exception;
use Log;

class ProductTypeSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $products;
    protected $jobMonitoringId;
    protected $uniqueJobName;
    protected $listing;
    protected $productType;

    public function __construct($products)
    {
        $this->products = $products;
        $this->productType  = app(ProductType::class);
    }

    public function handle()
    {
        $this->productType->setSellerConfig(true);
        $appName        = $this->productType->app->getAppName();
        $sellerId       = $this->productType->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->productType->seller->config['marketplace_id'];
        $count          = $this->products->count();

        \Log::info("Job with total counts of [{$count }] for Seller [{$appName}]");

        foreach ($this->products as $item) {
            $sku        = $item->getSku();
            $itemName   = $item->getItemName();
            $asin       = $item->getAsin();
            $response   = $this->productType->getSuggestedProductType($marketplaceId, [$asin, $itemName]);

            if (isset($response['error']) || isset($response['product_types']) && $response['product_types'] == []) {
                continue;
            }

            $productType = $response['product_types'][0]->getName() ?? null;

            if (is_null($productType)) {
                continue;
            }

            if (!$item->update(['product_type' => $productType])) {
                $message = "error on sku [{$sku}]";
                \Log::error($message);
            }
        }
    }
}