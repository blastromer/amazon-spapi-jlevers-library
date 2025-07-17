<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Models\MongoDB\ListingLog;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Services\Listing;

class AmazonUploadListingProductCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:upload:listing {--s|seller=}';
    protected $description  = 'List product in amazon using PUT not using Feed File.';
    private $log_info = '';
    protected $listing;

    public function __construct(Listing $listing
    ) {
        parent::__construct();
        $this->listing = $listing;
    }

    public function handle()
    {
        $process_start = Carbon::now()->toDateTimeString();

        try {
            $seller = $this->option('seller');
            $amazon = new Amazon();
            $seller_info = $amazon->checkSeller($seller);

            $this->log_info = "Command:" . $this->signature . "\n\n";

            if (is_null($seller_info)) {
                throw new Exception('Seller (' . $seller . ") does not exists!\n");
            }

            $loop_bool = true;
            $number_of_records = 1;
            $channels = $seller_info->channel;
            $warehouses = $seller_info->channel_inventory;
            $current_page = 1;

            $this->listing->setSellerConfig(true);
            $configuration = $amazon->setConfig($seller_info);

            do {
                $rows = AmazonListing::whereNotNull('sku')//where('sku', '100035po')//->//
                        ->whereSeller($seller_info->website)->orderBy('sku', 'ASC')->paginate($number_of_records, ['*'], 'page', $current_page);

                $total_page = $rows->lastPage();

                if ($current_page > $total_page) {
                    $loop_bool = false; // Kill loop
                }

                $counter = 1;
                $microtime_start = microtime(true);

                foreach ($rows as $row) {
                    $status = 'succcess';
                    $microtime_end = microtime(true);
                    $microtime_total = $microtime_end - $microtime_start;

                    if ($microtime_total < 1 && $counter >= 5) {
                        $microtime_start = microtime(true);
                        $counter = 0;
                        sleep(1);
                    }

                    try {
                        $issueLocale = 'en_US';
                        $response = $this->listing->putListing($row, $seller_info, $issueLocale);

                        $result = json_decode(json_encode($response['issues']));

                        if (count($response['issues']) > 0) {
                            $status = 'fail';
                        }

                    } catch (Exception $e) {
                        $status = 'error';
                        $result = json_encode($e->getMessage());
                    }

                    $log = ListingLog::where('sku', $row->sku)->first();

                    if (is_null($log)) {
                        ListingLog::create([
                            'sku' => $row->sku,
                            'status' => $status,
                            'response' => $result
                        ]);
                    } else {
                        $log->status = $status;
                        $log->response = $result;
                        $log->save();
                    }

                    $counter++;
                }

                $current_page++;

            } while($loop_bool);

        } catch (Exception $e) {
            $this->log_info .= ' ' . json_encode($e->getMessage());
        }

        $process_end = Carbon::now()->toDateTimeString();

        $this->info("Process Start:\t$process_start\nProcess End:\t$process_end\n" . $this->log_info);

        /*
        try {

            //$schema = $this->setSchema($row, $seller_info);
            //dd($schema);
            //$marketplaceIds = [$seller_info->marketplace_id];
            $apiInstance = new ListingsV20210801Api($configuration);
            //$apiInstance = new ListingsV20210801Api($configuration);

            $result = $apiInstance->getListingsItem('A2G5859HCU1M8W', '820596kw', ['ATVPDKIKX0DER']);
            //$included_data = ['issues','attributes','summaries','offers','fulfillmentAvailability'];
            //$response = $apiInstance->getListingsItem($seller_info->amazon_merchant_id, $row->sku, $marketplaceIds, $issueLocale, $included_data);dd($response);
            //$response   = $apiInstance->putListing($seller_info->amazon_merchant_id, $row->sku, $marketplaceIds, $schema, $issueLocale);
            dump($result);
        } catch (\Exception $e) {
            dd($e);
        }
        */
    }
}
