<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Jobs\ProductTypeSyncJob;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonAsinProductType;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SynListingProductTypeCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:listing:product-type:sync';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(
        ProductType $productType,
        AmazonQualifying $amazonQualifying,
        AmazonListing $amazonListing,
        AmazonAsinProductType $amazonAsinProductType,
        Product $product
    ) {
        parent::__construct();
        $this->productType      = $productType;
        $this->amazonQualifying = $amazonQualifying;
        $this->amazonListing    = $amazonListing;
        $this->amazonAsinProductType    = $amazonAsinProductType;
        $this->product    = $product;
    }

    public function handle()
    {
        $this->productType->setSellerConfig(true);
        $appName        = $this->productType->app->getAppName();
        $sellerId       = $this->productType->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->productType->seller->config['marketplace_id'];

        $items = $this->amazonListing
            ->where('seller', $appName)
            ->where('product_type', 'LIKE', '%"error"%');

        $items->chunk(500, function ($products) {
                ProductTypeSyncJob::dispatch($products)->onQueue('high');
            });
        return;

        $items = $items->get();
        $progressbar = $this->output->createProgressBar($items->count());
        $progressbar->setFormat('Fixing Amazon Listing ProductType %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressbar->start();

        foreach ($items as $item) {
            $progressbar->advance();
            $sku        = $item->getSku();
            $itemName   = $item->getItemName();
            $asin       = $item->getAsin();
            $productId  = preg_replace('/\D/', '', $sku);

            $product = $this->amazonAsinProductType
                ->where('asin', $asin)
                ->first();

            $productType = $product ? $product->getProductType() : null;

            if (!$product) {
                $product    = $this->product->where('productId', (int) $productId)->first();
                $brand      = $asin ?? null;
                if ($product) {
                    $brand = $product->getBrand();
                }

                $response   = $this->productType->getSuggestedProductType($marketplaceId, [$brand, $itemName]);

                if (isset($response['error']) || isset($response['product_types']) && $response['product_types'] == []) {
                    continue;
                }

                $productType = $response['product_types'][0]->getName() ?? null;
            }

            if (is_null($productType)) {
                continue;
            }

            if (!$item->update(['product_type' => $productType])) {
                $this->error("error on sku [{$sku}]");
            }
        }

        $progressbar->finish();
    }
}