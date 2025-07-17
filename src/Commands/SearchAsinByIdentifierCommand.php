<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class SearchAsinByIdentifierCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi-test:search:asin';
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

        // $response = $this->catalog->getCatalogItemList('707486404079');
        $response = $this->catalog->searchAsinByIndentifier('707486404079');

        dump($response);
    }
}