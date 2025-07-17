<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLogs;
use Typhoeus\JleversSpapi\Models\MySql\SdOrder;
use Typhoeus\JleversSpapi\Models\MySql\SdOrderAmazonFees;
use Typhoeus\JleversSpapi\Services\Finance;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class AmazonFeeDownloadCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi:download:fees';
    protected $description  = 'Amazon order Fees';
    private $log_info = '';
    private $logs_data = [];
    protected $finance;

    public function __construct(Finance $finance
    ) {
        parent::__construct();
        $this->finance = $finance;
    }

    public function handle()
    {
        try {
            $process_start = Carbon::now()->toDateTimeString();
            $this->log_info = "Command:" . $this->signature . "\n\n";

            $this->finance->setSellerConfig(true);
            $loop_bool = true;
            $number_of_records = 50;
            $current_page = 1;
            $website = env('APP_NAME');
            $page = intval(date('h'));//$page = 6;

            $query = SdOrder::whereRaw('STR_TO_DATE(OrderDate, "%Y-%m-%d") >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->where('Website', $website)->where('AmazonFee', 0.001)->where('ShipStatus', '<>', 'Cancelled')->whereRaw('SUBSTR(ShipName, 1, 3) <> \'FBA\'')->orderBy('OrderDate', 'DESC');
            $row_count = $query->count();
            $last_page = ceil($row_count/$number_of_records);

            if ($page > $last_page) {
                $page = rand(1, $last_page);
            }

            $rows = $query->paginate($number_of_records, ['*'], 'page', $page);

            foreach ($rows as $row) {
                $this->log_info .= $row->OrderId;
                //dump($row);
                try {
                    $message = '';
                    $total_fee = 0;
                    $amazon_order_id = $row->OrderId;

                    $amazon_fees = SdOrderAmazonFees::whereOrderId($amazon_order_id)->count();

                    if ($amazon_fees == 0) {
                    //if (true) {
                        $finance = $this->finance->listFinancialEventsByOrderId($amazon_order_id);

                        foreach ($finance->getFinancialEvents()->getShipmentEventList() as $event) {

                            foreach ($event->getShipmentItemList() as $row_item) {

                                foreach ($row_item->getItemFeeList() as $item_fee) {
                                    //dump($item_fee->getFeeAmount()->getCurrencyCode());
                                    $total_fee += $item_fee->getFeeAmount()->getCurrencyAmount();
                                }
                            }
                        }

                        $total_fee = abs($total_fee);

                        $this->log_info .= ' = ' . $total_fee;

                        if ($total_fee > 0) {
                            SdOrderAmazonFees::create([
                                'in_eclipse' => 'no',
                                'website' => $website,
                                'order_id' => $amazon_order_id,
                                'fee' => $total_fee,
                            ]);

                            $this->log_info .= " Inserted!";

                            SdOrder::where('OrderId', $amazon_order_id)->update(['AmazonFee' => $total_fee]);
                        } else {
                            $message = 'Fee not yet available.';
                        }
                        sleep(2);
                    } else {
                        $this->log_info .= " Exists!";
                    }

                    $this->log_info .= "\n";

                    $this->logs_data[] = [
                        'amazon_order_id' => $row->OrderId,
                        'eclipse_id' => $row->EclipseId,
                        'order_date' => $row->OrderDate,
                        'status' => 'success',
                        'fee' => $total_fee,
                        'message' => $message
                    ];
                } catch (Exception $e) {
                    $this->logs_data[] = [
                        'amazon_order_id' => $amazon_order_id,
                        'eclipse_id' => $row->EclipseId,
                        'order_date' => $row->OrderDate,
                        'status' => 'error',
                        'fee' => 'N/A',
                        'message' => $e->getMessage()
                    ];

                    if ($e->getCode() == 429) {
                        sleep(5);
                        $loop_bool = false; // Kill loop
                    }
                }
            }

        } catch (Exception $e) {

            $result = json_encode($e->getMessage());

            $this->logs_data[] = [
                'amazon_order_id' => $amazon_order_id,
                'status' => 'error',
                'message' => $result
            ];

            if ($e->getCode() == 429) {
                $loop_bool = false; // Kill loop
            }
        }
        
        $process_end = Carbon::now()->toDateTimeString();

        $this->storeLogs($website, $this->signature, $process_start, $process_end, $this->logs_data);

        $subject = $website . ' - Fees';
        $blade = 'amz-spapi::download-fees';
        $process = 'download-fees';
        $data = [
            'signature' => $this->signature,
            'process_start' => $process_start,
            'process_end' => $process_end,
            'total_count' => $row_count,
            'page' => $page,
            'rows' => $this->logs_data
        ];

        $this->sendMail($subject, $website, $data, $blade, $process);

        $this->info($this->log_info);
    }

}
