<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Illuminate\Support\Facades\Storage;

class PullItemIssuesCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:pull:items {sku}';
    protected $description = 'This command will patch or change the specific field';

    protected $listing;
    public $vendorInitial = [
        'po' => 'plumbersstock',
        'kw' => 'kentucky'
    ];
    public $defaultVendor       = ['po', 'kw'];
    public $primaryVendors      = ['plumbersstock'];
    public $secondaryVendors    = ['kentucky'];
    public $buffer              = 0;

    public function __construct(Listing $listing, Product $products)
    {
        parent::__construct();
        $this->listing = $listing;
    }

    public function handle()
    {
        // dd(123123213);
        // $this->info('Adding new Listings Item...');
        $sku = $this->argument('sku');
        // dd($sku);
        $includedData   = 'issues,summaries,attributes,offers,fulfillmentAvailability,procurement';
        $this->listing->setSellerConfig(true);
        $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $responseIssue  = $this->listing->getItem($sellerId, $sku, $marketplaceId, $issueLocale = 'en_US', $includedData);

        dump($responseIssue);
    }
}