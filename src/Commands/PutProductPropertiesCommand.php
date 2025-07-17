<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MongoDB\ProductAttribute;
use Typhoeus\JleversSpapi\Models\MongoDB\ProductType as ProductTypes;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PutProductPropertiesCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:product-attributes:put';
    protected $description = 'This command will patch or change the specific field';

    public $covered = ['product_details', 'product_identity', 'safety_and_compliance', 'offer', 'shipping'];
    public function __construct(
        ProductType $productType,
        AmazonQualifying $amazonQualifying,
        ProductAttribute $productAttribute,
        ProductTypes $productTypes
    ) {
        parent::__construct();
        $this->productType      = $productType;
        $this->amazonQualifying = $amazonQualifying;
        $this->productAttribute = $productAttribute;
        $this->productTypes     = $productTypes;
    }

    public function handle()
    {
        $this->productType->setSellerConfig(true);
        $sellerId           = $this->productType->seller->config['amazon_merchant_id'];
        $marketplaceId      = $this->productType->seller->config['marketplace_id'];
        $groupProductType   = $this->productTypes->get();
        $covered            = $this->covered;
        foreach ($groupProductType as $type) {
            $productType    = $type->getProductTypeName();
            if ($this->isExists($productType)) {
                $this->info("Product Type [{$productType}] Attribute is Existing...");
                continue;
            }
            $response       = $this->productType->fetchProductDefinition($productType, $marketplaceId, $sellerId);
            $filteredData   = array_filter($response->getPropertyGroups(), function ($key) use ($covered) {
                return in_array($key, $covered);
            }, ARRAY_FILTER_USE_KEY);
            $attributes     = collect($filteredData)
                ->map(function ($group) {
                    return method_exists($group, 'getPropertyNames') ? $group->getPropertyNames() : []; // Use getter method
                })
                ->flatten()
                ->unique()
                ->values()
                ->toArray();
            $query          = ['category' => $productType];
            $data           = ['attributes' => $attributes];
            $this->productAttribute->updateOrCreate($query, $data);
            $this->info("Updated the product type [{$productType}]...");
        }
    }

    public function isExists($type)
    {
        $productType = $this->productAttribute->where('category', $type)->exists();

        return $productType;
    }
}