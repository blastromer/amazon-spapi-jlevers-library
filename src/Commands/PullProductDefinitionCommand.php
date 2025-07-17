<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\SdShipment;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PullProductDefinitionCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:product-type:pull {productType}';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(ProductType $productType)
    {
        parent::__construct();
        $this->productType = $productType;
    }

    public function handle()
    {
        $this->productType->setSellerConfig(true);
        $productType = $this->argument('productType');
        // dd($productType);
        // $productType    = "APPAREL_PIN";
        $sellerId       = $this->productType->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->productType->seller->config['marketplace_id'];
        $response = $this->productType->fetchProductDefinition($productType, $marketplaceId, $sellerId);

        dump($response);
    }
}