<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class PatchProductCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi:patch:product';
    protected $description  = 'This command will patch or change the specific field';

    protected $listing;
    protected $pricing;
    protected $feed;

    public $vendorInitial   = [
        'plumbersstock' => [
            'po',
            ''
        ],
        'kentucky' => 'kw'
    ];

    public function __construct(Listing $listing) {
        parent::__construct();
        $this->listing = $listing;
    }

    public function handle()
    {
        $this->info('Patching product details...');
        $sellerConfig   = $this->listing->setSellerConfig(true);
        $products = Product::where('channels.amazonCraft', 'exists', true)
                            ->where('channels.amazonCraft', true)
                            ->where(['inventory.availability.plumbersstock.qty' => ['$gt' => (int) 2 ]])
                            // ->where(['inventory.availability.kentucky.qty' => ['$gt' => (int) 0 ]])
                            ->get(['productId', 'inventory']);
        // dd($products->count());
        $qualifiedSKU = [];
        $bar = new ProgressBar(new Console(), $products->count());
        $bar->setFormat('Processing %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        foreach ($products as $product) {
            $bar->advance();
            $motherSKU = $product->getProductId();
            $validSKU = [];
            foreach ($product->toArray()['inventory']['availability'] as $vendor => $data) {
                $isValidSKU = [];
                if ($vendor == 'swplumbing') {
                    continue 1;
                }

                if (isset($this->vendorInitial[$vendor])) {
                    if (is_array($this->vendorInitial[$vendor])) {
                        foreach ($this->vendorInitial[$vendor] as $vendorInit) {
                            $isValidSKU = $this->listing->getItemBySKU($motherSKU . $vendorInit);
                            if (isset($isValidSKU['error']) || $isValidSKU == []) {
                                continue;
                            }

                            $validSKU[$motherSKU . $vendorInit] = $data['qty'];
                        }
                    } else {

                        $isValidSKU = $this->listing->getItemBySKU($motherSKU . $this->vendorInitial[$vendor]);
                        if (isset($isValidSKU['error']) || $isValidSKU == []) {
                            continue;
                        }
                        $validSKU[$motherSKU . $this->vendorInitial[$vendor]] = $data['qty'];
                    }
                }
            }
            $qualifiedSKU[$motherSKU] = $validSKU;
            break;
        }
        $bar->finish();

        $body = [];

        foreach ($qualifiedSKU as $avails) {
            foreach ($avails as $sku => $avail) {
                $report = $this->listing->patchItem(
                    $sku,
                    $attr = 'fulfillment_availability',
                    $value = [
                        [
                            'fulfillment_channel_code' => 'DEFAULT',
                            'quantity' => (int) $avail
                        ]
                    ]
                );

                if (isset($report['status']) && $report['status'] == 'ACCEPTED') {
                    $this->info("Submitted Successfull for [{$sku}]: {$report['submission_id']}");
                }
                dd('test1');
            }
        }

        // $reports = $this->listing->patchItems(
        //     'fulfillment_availability',
        //     $body
        // );

        // foreach ($reports as $key => $report) {
        //     if (isset($report['status']) && $report['status'] == 'ACCEPTED') {
        //         $this->info("Submitted Successfull for [{$key}]: {$report['submission_id']}");
        //     }
        // }
    }
}
