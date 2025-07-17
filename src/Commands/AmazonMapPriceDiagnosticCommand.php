<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Storage;

class AmazonMapPriceDiagnosticCommand extends Command
{
    use ConsoleOutput, EmailNotification;

    protected $signature    = 'amz-spapi:diagnostic:map-price';
    protected $description  = 'Amazon Diagnostic Map Price';
    private $log_info = '';
    private $logs_data = [];
    protected $finance;

    public function __construct(
    ) {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $process_start = Carbon::now()->toDateTimeString();
            $this->log_info = "Command:" . $this->signature . "\n\n";

            $website = env('APP_NAME');
            $loop_bool = true;
            $number_of_records = 100;
            $current_page = 1;
            $map_methods = ['hard', 'HARD', 'Hard'];

            $filename = date('Y-m-d') . '.csv';
            $file_path = $this->createFile($website, $filename);
            
            $fp = fopen($file_path, 'w');
            fputcsv($fp, ['Product Id', 'SKU', 'Listing Status', 'Listing Price', 'Map Price', 'Message']);

            do {

                $rows = AmazonListing::whereNotNull('status')->paginate($number_of_records, ['sku', 'price', 'status'], 'page', $current_page);

                $total_page = $rows->lastPage();

                if ($current_page > $total_page) {
                    $loop_bool = false; // Kill loop
                }

                foreach ($rows as $row) {
                    try {
                        $sku_raw = preg_replace('/\D/', '', $row->sku);
                        $sku_raw = intval($sku_raw);

                        $product = Product::where('productId', $sku_raw)->whereIn('pricing.mapMethod', ['hard', 'HARD', 'Hard'])->first(['productId', 'pricing']);

                        if (!is_null($product)) {
                            $listing_price = floatval($row->price);
                            $product_map_price = floatval($product->pricing['mapPrice']);

                            $status = 'success';
                            $message = '';

                            if ($row->price < $product_map_price) {

                                $status = 'error';
                                $message = 'Listing Price (' . $row->price . ')  is less than Map Price (' . $product_map_price . ').';

                                fputcsv($fp, [
                                    $sku_raw,
                                    $row->sku,
                                    $row->status,
                                    $listing_price,
                                    $product_map_price,
                                    $message
                                ]);
                            }
                        }

                    } catch (Exception $e) {

                        $result = json_encode($e->getMessage());

                        fputcsv($fp, [
                            $row->sku,
                            $row->sku,
                            null,
                            null,
                            null,
                            $result
                        ]);
                    }
                }

                $current_page++;

            } while($loop_bool);

            fclose($fp);
        
            $process_end = Carbon::now()->toDateTimeString();
            $subject = $website . ' - DIAGNOSTIC SKU Report';
            $blade = 'amz-spapi::report-sku';
            $process = $this->signature;
            $files[] = $file_path;
            $data = [
                'signature' => $process,
                'process_start' => $process_start,
                'process_end' => $process_end,
                'msg' => ''
            ];

            $this->sendMailWithAttachment($subject, $website, $data, $blade, $process, $files);

        } catch (Exception $e) {

            $subject = 'ERROR!!! - SKU Reporting(' . $website . ')';
            $result = json_encode($e->getMessage());
            $blade = 'amz-spapi::report-sku';
            $process = $this->signature;
            $data = ['msg'=>$result];

            $this->sendMail($subject, $website, $data, $blade, $process);
        }

    }

    public function createFile($website, $filename)
    {
        $date_now = date('Y-m-d');
        $storage = $website . '\\diagnostic\\';

        if(!Storage::exists($storage)){
            Storage::makeDirectory($storage);
        }

        $filePath = storage_path('app\\' . $storage . '' . $filename);

        return $filePath;
    }

}
