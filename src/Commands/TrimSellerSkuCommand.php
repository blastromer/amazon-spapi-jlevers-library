<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\ProductType;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;
use Carbon\Carbon;

class TrimSellerSkuCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:product-sku:trim';
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
        $this->info("Triming SKU to plain SKU...");
        $items = $this->amazonQualifying->all();
        // dump($items->count());
        $bar        = new ProgressBar(new Console(), $items->count());
        $bar->setFormat('Updating to Parent SKU %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach ($items as $item) {
            $bar->advance();
            $parentSku = str_replace(['kw', 'po'], '', $item->getSku());
            $item->update(['sku' => $parentSku]);
        }
        $bar->finish();
    }
}