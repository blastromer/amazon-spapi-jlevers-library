<?php

namespace Typhoeus\JleversSpapi\Commands;

use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonAsinProductType;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;

class AmazonGetProductTypeCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:get:product-type {--s|seller=}';
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

        try {
            $seller = $this->option('seller');
            $amazon = new Amazon();
            $seller_info = $amazon->checkSeller($seller);

            $this->log_info = "Command:" . $this->signature . "\n\n";

            if (is_null($seller_info)) {
                throw new Exception('Seller (' . $seller . ") does not exists!\n");
            }

            $this->catalog->setSellerConfig(true);
            $loop_bool = true;
            $number_of_records = 1000;
            $current_page = 1;

            do {
                $rows = AmazonListing::paginate($number_of_records, ['asin'], 'page', $current_page);
                $total_page = $rows->lastPage();

                if ($current_page > $total_page) {
                    $loop_bool = false; // Kill loop
                }

                $counter = 1;
                $microtime_start = microtime(true);

                foreach ($rows as $row) {
                    $this->info($row->asin);

                    $microtime_end = microtime(true);
                    $microtime_total = $microtime_end - $microtime_start;

                    if ($microtime_total < 1 && $counter >= 5) {
                        $microtime_start = microtime(true);
                        $counter = 0;
                        sleep(1);
                    }

                    try {
                        $count_row = AmazonAsinProductType::where('asin', $row->asin)->count();
                        if ($count_row == 0) {
                            $response = $this->catalog->getProductTypeByAsin($row->asin);
                            //dump($response);
                            AmazonAsinProductType::create(['asin'=>$row->asin, 'product_type'=>$response]);
                        } else {
                            //$this->info('Exists');
                        }

                    } catch (Exception $e) {
                        $result = json_encode($e->getMessage());
                        dump($result);
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
