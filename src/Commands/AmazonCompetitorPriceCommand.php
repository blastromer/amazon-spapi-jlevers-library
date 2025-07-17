<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MongoDB\CatalogItemAsin;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonCompetitivePrice;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class AmazonCompetitorPriceCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi:download:competitor-price';
    protected $description  = 'Catalog item collation command.';
    private $log_info = '';
    private $logs_data = [];
    protected $pricing;

    public function __construct(Pricing $pricing
    ) {
        parent::__construct();
        $this->pricing = $pricing;
    }

    public function handle()
    {
        $process_start = Carbon::now()->toDateTimeString();

        try {
            $this->log_info = "Command:" . $this->signature . "\n\n";
            $this->pricing->setSellerConfig(true);
            $website = $this->pricing->app->getAppName();
            $loop_bool = true;
            $number_of_records = 20;
            $current_page = 1;

            $success = 0;
            $fail = 0;
            do {
                $rows = AmazonListing::where('seller', $website)->paginate($number_of_records, ['*'], 'page', $current_page);

                $total_page = $rows->lastPage();

                if ($current_page > $total_page) {
                    $loop_bool = false; // Kill loop
                }

                $asins = [];
                foreach ($rows as $row) {
                    $asins[] = $row->asin;
                }

                try {
                    $item_type = ['Asin'];
                    $skus = null;
                    $customer_type = null;
                    $marketplace_id = 'ATVPDKIKX0DER';

                    $prices = $this->pricing->getCompetitivePricing($marketplace_id, $item_type, $asins, $skus, $customer_type);

                    foreach ($prices as $data) {

                        if ($data->getStatus() == 'Success') {
                            foreach ($data->getProduct()->getCompetitivePricing()->getCompetitivePrices() as $value) {
                                $asin = $data->getAsin();
                                //$this->info($asin);
                                $count = AmazonCompetitivePrice::whereWebsite($website)->where('asin', $asin)->count();
                                if ($count == 0) {
                                    AmazonCompetitivePrice::create([
                                        'website' => $website,
                                        'asin' => $asin,
                                        'landed_price' => $value->getPrice()->getLandedPrice()->getAmount(),
                                        'listing_price' => $value->getPrice()->getListingPrice()->getAmount()
                                    ]);
                                } else {
                                    AmazonCompetitivePrice::whereWebsite($website)->whereAsin($asin)
                                    ->update([
                                        'landed_price' => $value->getPrice()->getLandedPrice()->getAmount(),
                                        'listing_price' => $value->getPrice()->getListingPrice()->getAmount()
                                    ]);
                                }
                                $success++;
                            }
                        } else {
                            $fail++;
                        }
                    }
                    sleep(1);

                } catch (Exception $e) {
                    $result = $e->getMessage();
                }
                //$current_page++;

            } while($loop_bool);

        } catch (Exception $e) {
            $result = $e->getMessage();
        }

        $process_end = Carbon::now()->toDateTimeString();

        $subject = $website . ' - Competitor Price';
        $blade = 'amz-spapi::competitor-price';
        $process = 'competitor-price';
        $data = [
            'signature' => $this->signature,
            'process_start' => $process_start,
            'process_end' => $process_end,
            'success' => $success,
            'fail' => $fail
        ];

        $this->sendMail($subject, $website, $data, $blade, $process);
    }

}
