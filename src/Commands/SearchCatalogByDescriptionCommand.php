<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class SearchCatalogByDescriptionCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi-test:search-catalog:using-description';
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
        $this->info('Searching ASIN by Identifier...');
        $this->catalog->setSellerConfig(true);

        // $response = $this->catalog->searchAsinByItemName('ProStock 1-Inch Press Jaw for Copper - CPJ1');
        $response = $this->catalog->searchAsinByIndentifier('4053424309149');

        dump($response->items);
    }
}