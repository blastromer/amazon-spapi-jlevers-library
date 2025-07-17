<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Typhoeus\JleversSpapi\Helpers\ProductHelper;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQty;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;

class AmazonPatchItemsCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:patch:items
                            {--attribute=}
                            {--notification}
                            ';
    protected $description = 'This command will patch or change the specific field of each item qualified for update. Example attribute would be fulfillment_availability or purchasable_offer';

    public $attributes = ['fulfillment_availability', 'purchasable_offer'];

    public $amazonQty;

    public function __construct(
        ProductHelper $helper,
        AmazonListing $amazonListing,
        AmazonQty $amazonQty
    ) {
        parent::__construct();
        $this->helper           = $helper;
        $this->amazonQty        = $amazonQty;
        $this->amazonListing    = $amazonListing;
    }

    public function handle()
    {
        $this->info('Matching Inactive products...');
        $attribute = $this->option('attribute');
        if (!in_array($attribute, $this->attributes)) {
            $this->error("The option category is not accepting the value you provided, [{$attribute}] category is not valid.");
        }
        $qualifiedItems = $this->amazonQty->where('ready_for_upload', 1)->get();
        foreach ($qualifiedItems as $item) {
            $actualQty = $this->helper->getQtyAvailability($item->getSku());
            if ($actualQty && $actualQty != $item->getQty()) {
                dump($actualQty);
                dump($item->getQty());
                dump($item->getSku());
            }
        }
    }
}
