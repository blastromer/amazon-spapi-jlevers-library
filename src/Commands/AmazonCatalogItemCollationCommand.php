<?php

namespace Typhoeus\JleversSpapi\Commands;

use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MongoDB\CatalogItems;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;

class AmazonCatalogItemCollationCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:collate-catalog-item';
    protected $description  = 'Catalog item collation command.';
    private $log_info = '';
    protected $catalog;

    public function __construct(Catalog $catalog
    ) {
        parent::__construct();
        $this->catalog = $catalog;
    }

    public function handle()
    {
        date_default_timezone_set('America/Denver');

        try {
            $this->log_info = "Command:" . $this->signature . "\n\n";

            $this->catalog->setSellerConfig(true);
            $website = $this->catalog->app->getAppName();
            $loop_bool = true;
            $number_of_records = 1000;
            $current_page = 1;

            do {
                $rows = AmazonQualifying::where('seller', $website)->whereNull('product_type')->paginate($number_of_records, ['*'], 'page', $current_page);

                $total_page = $rows->lastPage();

                if ($current_page > $total_page) {
                    $loop_bool = false; // Kill loop
                }

                $counter = 1;
                $microtime_start = microtime(true);

                foreach ($rows as $row) {
                    $this->info($row->upc);
                    $status = 'succcess';
                    $microtime_end = microtime(true);
                    $microtime_total = $microtime_end - $microtime_start;

                    if ($microtime_total < 1 && $counter >= 5) {
                        $microtime_start = microtime(true);
                        $counter = 0;
                        sleep(1);
                    }

                    try {
                        $count_ci = CatalogItems::where('upc', $row->upc)->where('website', $website)->count();

                        if ($count_ci == 0) {
                            //$this->info('Inserted');
                            $response = $this->catalog->getCatalogItemList($row->upc)->getPayload();
                            //dump($response);
                            foreach ($response->items as $item) {
                                $item = (array)json_decode(json_encode($item));
                                $item['upc'] = $row->upc;
                                $item['website'] = $website;
                                //dump($item);
                                CatalogItems::create($item);
                            }
                        }

                    } catch (Exception $e) {
                        $result = json_encode($e->getMessage());
                        //dump($result);
                    }

                    $counter++;
                }
                $this->info('Page : ' . $current_page);

                $current_page++;

            } while($loop_bool);

        } catch (Exception $e) {
            dump(json_encode($e->getMessage()));
            //$this->log_info .= ' ' . json_encode($e->getMessage());
        }

        $this->info($this->log_info);
    }

}
