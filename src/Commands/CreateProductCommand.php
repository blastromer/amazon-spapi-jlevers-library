<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;

class CreateProductCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi:create:product';
    protected $description  = 'This command will update or create product in listing';

    protected $listing;
    protected $pricing;
    protected $feed;

    public function __construct(Catalog $catalog, Listing $listing) {
        parent::__construct();
        $this->catalog = $catalog;
        $this->listing = $listing;
    }

    public function handle()
    {
        $this->info('Updating or Creating product details...');
        // $this->catalog->setSellerConfig(true);
        // $suggestedASIN = $this->catalog->getSuggestedASIN();
        // dd($suggestedASIN);
        $sellerConfig   = $this->listing->setSellerConfig(true);
        $response = $this->listing->putListing();
        dd($response);
        if (isset($response['status']) && $response['status'] == 'ACCEPTED') {
            $this->info("Submitted Successfully: {$response['submission_id']}");
        } else {

        }
    }
}
