<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class SearchCatalogByAsinCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi-test:search-catalog:by-asin';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;
    public $amazonQualifying;

    public function __construct(
        Catalog $catalog
    ) {
        parent::__construct();
        $this->catalog  = $catalog;
    }

    public function handle()
    {
        $this->info('Get Product Type by Asin...');
        $this->catalog->setSellerConfig(true);
        // $response = $this->catalog->getProductTypeByAsin("B00125FARK");
        $response = $this->catalog->getCatalogItemByASIN("B008J527AY");

        // $response = $this->catalog->getCatalogItemList('685256239574');
        // $response = $this->catalog->searchAsinByIndentifier('685256239574');

        dump($response);
    }
}