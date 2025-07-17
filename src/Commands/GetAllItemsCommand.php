<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Services\Feed;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Services\Report;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;

class GetAllItemsCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:get:all:items';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;

    public function __construct(
        Listing $listing,
        Pricing $pricing,
        Feed $feed,
        Catalog $catalog,
        Report $report
    ) {
        parent::__construct();
        $this->listing  = $listing;
        $this->pricing  = $pricing;
        $this->feed     = $feed;
        $this->catalog  = $catalog;
        $this->report   = $report;
    }

    public function handle()
    {
        $this->info('Test Get All Items by Report...');
        $sellerConfigForListing = $this->report->setSellerConfig(true);
        $listAllItems           = $this->report->listAllItemsReport();
        dump($listAllItems->getReportId());
        if (!$listAllItems->getReportId()) {
            dd('failed');
        }
        $report = $this->report->getById($listAllItems->getReportId());

        dump($report);
        dd(1123123);
    }
}
