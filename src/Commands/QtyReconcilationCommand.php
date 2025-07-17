<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Services\Seller;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQty;

class QtyReconcilationCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi-test:quantity:reconcile';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;

    public function __construct(
        AmazonListing   $listing,
        AmazonQty       $qty,
        Seller          $seller
    ) {
        parent::__construct();
        $this->listing = $listing;
        $this->qty = $qty;
        $this->seller = $seller;
    }

    public function handle()
    {
        $appName = $this->seller->app->getAppName();
        $this->info("Reconciling Amazon QTY and Mysql Qty...");
        $amazonQty = $this->qty->where("seller", $appName)
            ->orderBy("id", "asc")
            ->get();

        // $amazonQty->count());
        foreach ($amazonQty as $list) {
            dump($list->sku);
            $item = $this->listing->where("sku", $list->sku)->first();
            if (!$item) {
                continue;
            }
            // dump($item->);
            dd();
        }

    }
}
