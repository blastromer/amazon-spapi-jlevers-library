<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Illuminate\Support\Facades\Storage;

class PatchListingTestCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:patch:availability';
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
        $this->products = $products;
    }

    public function handle()
    {
        // dd(123123213);
        $this->info('Adding new Listings Item...');
        $this->listing->setSellerConfig(true);
        $response = $this->listing->putListing();
        dump($response);
    }
}