<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class GenerateListingsByBuylineCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi-test:generate-listing:buyline';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;
    public $amazonQualifying;

    public function __construct(
        Product $product,
        Listing $listing,
        AmazonListing $amazonListing
    ) {
        parent::__construct();
        $this->product          = $product;
        $this->listing          = $listing;
        $this->amazonListing    = $amazonListing;
    }

    public function handle()
    {
        $listings = $this->amazonListing->all();

        if ($listings->isEmpty()) {
            $this->warn('No listings found.');
            return;
        }

        // CSV file path
        $filePath = storage_path('app/amazon_listings.csv');
        $file = fopen($filePath, 'w');

        // Define which fields you want to include
        $selectedFields = ['buy_line', 'sku', 'item_name', 'asin', 'price', 'qty', 'status']; // adjust as needed

        // Write header
        fputcsv($file, $selectedFields);
        $progressbar	= new ProgressBar(new Console(), $listings->count());
        $progressbar->setFormat('Processing %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressbar->start();
        foreach ($listings as $item) {
            $progressbar->advance();
            // Find related product using SKU
            $productId = (int) trim($item->getSku());
            $product = $this->product
                ->where('productId', $productId)
                ->first();

            // Get the buyline or fallback to empty string
            $buyLine = $product ? $product->getBuyLine() : '';

            // Collect data for the row
            $data = [
                'buy_line' => $buyLine,
                'sku' => $item->getSku(),
                'item_name' => $item->getItemName(),
                'asin' => $item->getAsin(),
                'price' => $item->getPrice(),
                'qty' => $item->getQty(),
                'status' => $item->getStatus(),
            ];

            fputcsv($file, $data);
        }

        fclose($file);
        $progressbar->finish();
        $this->info("CSV exported successfully to: {$filePath}");
    }
}