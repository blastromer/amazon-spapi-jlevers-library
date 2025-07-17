<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;

class AmazonQualifyProductsCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi:qualify:products';
    protected $description  = 'This command will search and qualify a product from catalog for new listing or non-existing product';

    protected $spapiService;

    public $branchs = [
        'po',
        'kw',
        ''
    ];

    public function __construct(
        Product $product,
        AmazonListing $amazonListing,
        Pricing $pricing,
        Catalog $catalog,
        AmazonQualifying $amazonQualifying
    ) {
        parent::__construct();
        $this->product          = $product;
        $this->amazonListing    = $amazonListing;
        $this->pricing          = $pricing;
        $this->catalog          = $catalog;
        $this->amazonQualifying = $amazonQualifying;
    }

    public function handle()
    {
        $appName    = $this->catalog->app->getAppName();
        $channel    = $this->catalog->app->getConfigChannel($appName);
        $this->info("Getting for products to be qualified for [{$appName}] listing...");
        $query = [
            'channels.' . $channel =>  true
        ];

        $products   = $this->product->where($query)->get(['productId']);
        $bar        = new ProgressBar(new Console(), $products->count());
        $bar->setFormat('Processing %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach ($products as $product) {
            $parentSku = $product->getProductId();
            foreach ($this->branchs as $branch) {
                $sellerSku = $parentSku . $branch;
                if ($this->amazonListing->where('sku', $sellerSku)->doesntExist()) {
                    \Log::info("this sku/product [{$parentSku}{$branch}] was not found\n");
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info("Searching complete...");
    }
}