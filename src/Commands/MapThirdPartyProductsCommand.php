<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Illuminate\Support\Facades\Storage;

class MapThirdPartyProductsCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = "amz-spapi-test:third-party-products:mapping";
    protected $description = "This command will map all the product from third party feed and check if it is qualified to list in amazon";

    public $vendors = [
        '8' => [
            'stockmarket',
            'orgill',
            'tigris_atl',
            'tigris_slc'
        ],
        '11' => [
            'orgill_mo'
        ]
    ];

    public $appChannel = [
        ''
    ];

    public function __construct(Product $product, Catalog $catalog) {
        parent::__construct();
        $this->product = $product;
        $this->catalog = $catalog;
    }

    public function handle()
    {
        $this->catalog->setSellerConfig(true);
        $appName = $this->catalog->app->getAppName();
        $channel = $this->catalog->app;
        dd($channel);
        $branches = $this->vendors;
        foreach ($branches as $branch) {
            foreach ($branch as $vendor) {
                $this->info("Mapping of Products from Third party vendor [{$vendor}]");
                $products = $this->product->where("inventory.availability.{$vendor}", 'exists', true)
                    ->where("channels.{$appName}", true);
                dump($products->count());
            }
        }
    }
}