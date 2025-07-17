<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\SdShipment;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SearchProductTypeCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:product-type:generate';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(ProductType $productType)
    {
        parent::__construct();
        $this->productType = $productType;
    }

    public function handle()
    {
        $this->productType->setSellerConfig(true);
        $response = $this->productType->getSuggestedProductType('ATVPDKIKX0DER', ['ProStock','Press Jaw for Copper', '']);

        dump($response);
    }
}