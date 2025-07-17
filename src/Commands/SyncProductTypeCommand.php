<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SyncProductTypeCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:product-type:sync';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(
        ProductType $productType,
        AmazonQualifying $amazonQualifying,
        Product $product
    ) {
        parent::__construct();
        $this->productType      = $productType;
        $this->amazonQualifying = $amazonQualifying;
        $this->product          = $product;
    }

    public function handle()
    {
        $this->productType->setSellerConfig(true);
        $qualifiedProduct = $this->amazonQualifying->all();;
        foreach ($qualifiedProduct as $product) {
            $upc            = $product->getUpc();
            $mongoProduct   = $this->product->where('upc', $upc)->first() ?? [];
            if ($mongoProduct != []) {
                $productType    = null;
                $response       = $this->productType->getSuggestedProductType('ATVPDKIKX0DER', [$product->getBrand(), $mongoProduct->getKeywords(), $product->getTitle(), $product->getUpc()]);
                if ($response['product_types'] == []) {
                    $amazon     = $mongoProduct->getAmazon();
                    $content    = $amazon['content'] ?? null;
                    $attributes = $content['attributes'] ?? [];
                    if ($attributes != []) {
                        $productType = $attributes['ProductTypeName'] ?? null;
                    }
                    if ($productType == []) {
                        $productType = $product->getProductType();
                    }
                    if ($product->update(['product_type' => $productType]) ){
                        $this->info("Nothing to get from UPC [{$upc}], instead it will get from MongoDB Product Type [{$productType}]: [SYNCED]...");
                    }
                    continue;
                }
                $types          = $response['product_types'] ?? [];
                $productType    = empty($types[0]['name']) ? null : $types[0]['name'];
                if ($product->update(['product_type' => $productType])) {
                    $this->info("Found a Product Type [{$productType}] from UPC [{$upc}]: [SYNCED]...");
                }
            }
        }
    }
}