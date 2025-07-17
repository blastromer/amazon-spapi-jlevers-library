<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class PullItemByAsinCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi-test:pull:item';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;
    public $amazonQualifying;

    public function __construct(
        Catalog $catalog,
        Listing $listing,
        AmazonListing $amazonListing
    ) {
        parent::__construct();
        $this->catalog          = $catalog;
        $this->listing          = $listing;
        $this->amazonListing    = $amazonListing;
    }

    public function handle()
    {
        $this->info('Pulling Item by ASIN...');
        $this->catalog->setSellerConfig(true);
        $this->listing->setSellerConfig(true);

        $items = $this->amazonListing
            ->where('seller', $this->listing->app->getAppName())
            // ->where('status', 'Incomplete')
            // ->where('status', 'Active')
            ->whereNull('product_type')
            ->whereNotNull('asin')
            ->groupBy('asin')
            ->get();
        // dump($items->count());
        $progressbar        = new ProgressBar(new Console(), $items->count());
        $progressbar->setFormat('Update Product Type %current%/%max% [%bar%] %percent:3s%% Elapsed: %elapsed:6s% Estimated: %remaining:6s% Memory: %memory:6s%');
        $progressbar->start();
        // dd($items->count());
        foreach ($items as $item) {
            $progressbar->advance();
            // $response = $this->catalog->getProductTypeByAsin($item->getAsin());
            // $response = $this->catalog->getCatalogItemByASIN($item->getAsin());
            // dd($item->getSku());
            $response = $this->listing->getProductDetails($item->getSku(), 'product_type');
            // dump($response);
            if (isset($response['error'])) {
                dump($response['error']);
                continue;
            } else {
                // dd($response);
                if ($item->update(['product_type' => $response])) {
                    // $this->info("ASIN [{$item->getAsin()}] was successfully update with product type [{$response}]");
                }
            }
        }
        $progressbar->finish();
        // $response = $this->catalog->getCatalogItemByASIN('B06XDVF66R');
        // $this->catalog->getProductTypeByAsin($asin);
        // dump($response);
    }
}