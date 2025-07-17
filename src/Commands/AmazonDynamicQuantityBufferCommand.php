<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQty;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyBuffer;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyBufferLog;
use Typhoeus\JleversSpapi\Models\MySql\SdShipment;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class AmazonDynamicQuantityBufferCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi:generate:qty-buffer';
    protected $description  = 'Dynamic Quantity Buffer command.';
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
            $number_of_records = 1000;
            $current_page = 1;

            $success = 0;
            $fail = 0;
            do {
                $rows = AmazonListing::where('seller', $website)->paginate($number_of_records, ['*'], 'page', $current_page);

                $total_page = $rows->lastPage();

                if ($current_page > $total_page) {
                    $loop_bool = false; // Kill loop
                }

                try {
                    foreach ($rows as $row) {

                        $qty_buffer = 0;
                        $sku = preg_replace("/[^0-9]/", '', $row->sku);

                        $product = Product::where('productId', intval($sku))->first(['productId', 'buyLine']);

                        if (!in_array($product->buyLine, ['DISCONTI'])) {
                            $months_dates = SdShipment::select([//'ProductId', 
                                                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d") as created_at_date'), 
                                                DB::raw('COUNT(Qty) as qty_count'), 
                                                DB::raw('SUM(Qty) as qty_sum')
                                            ])
                                            ->where('ProductId', $sku)->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->groupByRaw('created_at_date')
                                            ->get();

                            $qty_sum = $months_dates->sum('qty_sum');
                            $qty_count = $months_dates->sum('qty_count');

                            if ($qty_sum > 0 && $qty_count > 0) {
                                $qty_buffer = ceil($qty_sum / $qty_count);
                            }

                            if ($qty_buffer > $row->qty && $row->qty < 1) {
                                $qty_buffer = 0;
                            } elseif ($qty_buffer > $row->qty && $row->qty < 2) {
                                $qty_buffer = 1;
                            } elseif ($qty_buffer > $row->qty && $row->qty < 10) {
                                $qty_buffer = $qty_buffer / 2;
                            }
                        }

                        $data_row = AmazonQtyBuffer::whereSeller($website)->whereSku($row->sku)->first();

                        if (is_null($data_row)) {
                            AmazonQtyBuffer::create([ 'seller' => $website, 'sku' => $row->sku, 'qty' => $qty_buffer ]);
                        } else {
                            $data_row->qty = $qty_buffer;
                            $data_row->save();
                        }
                        AmazonQtyBufferLog::create([ 'seller' => $website, 'sku' => $row->sku, 'qty' => $qty_buffer ]);

                        /*
                        dump($row->sku);
                        dump('Monthly Sum = ' . $qty_sum);
                        dump('Monthly Count = ' . $qty_count);
                        dump('QtyBuffer = ' . $qty_buffer);
                        dump('Inventory = ' . $row->qty);
                        dump('----------------------');
                        */
                    }

                } catch (Exception $e) {
                    $result = $e->getMessage();
                    dump($result);
                }
                //dump('=================================Page : ' . $current_page);
                //$loop_bool = false; // Kill loop
                $current_page++;
            } while($loop_bool);

            $process_end = Carbon::now()->toDateTimeString();

            $subject = $website . ' - Dynamic Quantity Buffer';
            $blade = 'amz-spapi::dynamic-quantity-buffer';
            $process = 'dynamic-quantity-buffer';
            $data = [
                'signature' => $this->signature,
                'process_start' => $process_start,
                'process_end' => $process_end
            ];

            $this->sendMail($subject, $website, $data, $blade, $process);

        } catch (Exception $e) {
            $result = $e->getMessage();
            dump($result);
        }
    }

}
