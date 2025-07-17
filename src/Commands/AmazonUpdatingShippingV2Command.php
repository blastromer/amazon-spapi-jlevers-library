<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MySql\SdItem;
use Typhoeus\JleversSpapi\Models\MySql\SdOrder;
use Typhoeus\JleversSpapi\Models\MySql\SdShipment;
use Typhoeus\JleversSpapi\Services\Order;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class AmazonUpdatingShippingV2Command extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi:update:shipping-v2';
    protected $description  = 'Amazon updating order shipping information verion 2 command.';
    private $log_info = '';
    private $logs_data = [];
    protected $order;

    public function __construct(Order $order
    ) {
        parent::__construct();
        $this->order = $order;
    }

    public function handle()
    {
        try {
            $process_start = Carbon::now()->toDateTimeString();

            $this->order->setSellerConfig(true);
            $loop_bool = true;
            $website = env('APP_NAME');
            $now = Carbon::now();
            $now->subDays(90);

            //$rows = SdOrder::where('WebSite', $website)->whereRaw('OrderDate>DATE_SUB(NOW(), INTERVAL 30 DAY)')
            $rows = SdOrder::where('WebSite', $website)->whereRaw('DATE_FORMAT(OrderDate, "%Y-%m-%d %H:%i:%s") > DATE_SUB(NOW(), INTERVAL 30 DAY)')
                    ->whereIn('EclipseId', function($query) use ($now){
                        $query->select('EclipseId')->from('sd_shipments')->where('ShipStatus', 'Pending')->where('created_at', '>', $now->format('Y-m-d'))->where('TrackingNumber', '<>', '');
                    })
                    ->limit(100)->get();
            //$rows = SdOrder::where('EclipseId', 'S4971346')->limit(100)->get();

            foreach ($rows as $row) {
                try {
                    $this->log_info .= $row->OrderId . "\n";
                    $order_id = $row->OrderId;
                    $eclipse_id = $row->EclipseId;


                    $sd_items = SdItem::where('OrderId', $order_id)->where('EclipseId', $eclipse_id)->get();

                    foreach ($sd_items as $item) {
                        try {
                            $shipment = SdShipment::where('EclipseId', 'LIKE', $eclipse_id . '%')->where('ProductId', $item->Sku)->first();

                            if (!is_null($shipment)) {

                                $packageReferenceId = $shipment->cartonId;

                                if (empty($shipment->cartonId)) {
                                    $packageReferenceId = preg_replace('/\D/', '', $shipment->EclipseId) . '' . $shipment->ShipDate;
                                }

                                $payload = [
                                    'packageDetail' => [
                                        'packageReferenceId' => $packageReferenceId,
                                        'carrierCode' => $shipment->CarrierType,
                                        'shipDate' => date('Y-m-d\TH:m:i\Z', strtotime($shipment->ShipDate)),
                                        'shippingMethod' => $shipment->CarrierMethod,
                                        'trackingNumber' => $shipment->TrackingNumber,
                                        'orderItems' => [
                                            [
                                                'orderItemId' => $item->OrderItemId,
                                                'quantity' => intval($item->QuantityOrdered)
                                            ]
                                        ]
                                    ],
                                    'marketplaceId' => 'ATVPDKIKX0DER'
                                ];

                                SdShipment::where('EclipseId', $shipment->EclipseId)->where('cartonId', $shipment->cartonId)->update(['ShipStatus' => 'Processed']);

                                $response = $this->order->confirmShipment($order_id, $payload);
                                dump($response);

                                $this->log_info .= " Success \n";

                                $this->logs_data[] = [
                                    'amazon_order_id' => $order_id,
                                    'eclipse_id' => $eclipse_id,
                                    'status' => 'success',
                                    'message' => $response
                                ];

                                sleep(1);
                            } else {
                                $this->log_info .= " No Shipping Details \n";
                                $this->logs_data[] = [
                                    'amazon_order_id' => $order_id,
                                    'eclipse_id' => $eclipse_id,
                                    'status' => 'error',
                                    'message' => 'No Shipping Details'
                                ];
                            }
                        } catch (Exception $e) {
                            $result = $this->clearResult($e->getMessage());
                            $this->log_info .= ($result->errors[0]->message ?? '') . "\n";
                            $this->logs_data[] = [
                                'amazon_order_id' => $order_id,
                                'eclipse_id' => $eclipse_id,
                                'status' => 'error',
                                'message' => $result->errors[0]->message ?? ''
                            ];
                        }
                    }// foreach

                } catch (Exception $e) {
                    $result = $e->getMessage();
                    $this->log_info .= $result . "\n";
                    $this->logs_data[] = [
                        'amazon_order_id' => $order_id,
                        'eclipse_id' => $eclipse_id,
                        'status' => 'error',
                        'message' => json_decode($result)
                    ];
                }
            }// foreach

        } catch (Exception $e) {
            $result = $e->getMessage();
            $this->log_info .= ' ' . $result;
            $this->logs_data[] = [
                'amazon_order_id' => $order_id,
                'eclipse_id' => $eclipse_id,
                'status' => 'error',
                'message' => $result
            ];
        }
        $process_end = Carbon::now()->toDateTimeString();
        
        $this->storeLogs($website, $this->signature, $process_start, $process_end, $this->logs_data);
        $this->log_info = "\nStart:\t$process_start\nEnd:\t$process_end\nCmd:\t" . $this->signature . "\n\n" . $this->log_info;

        $subject = $website . ' - Shipping';
        $blade = 'amz-spapi::update-shipping-info';
        $process = 'update-shipping';
        $data = [
            'signature' => $this->signature,
            'process_start' => $process_start,
            'process_end' => $process_end,
            'rows' => $this->logs_data
        ];

        $this->info($this->log_info);
        $this->sendMail($subject, $website, $data, $blade, $process);
        $this->sendErrors($website, $data);
    }

    private function clearResult($result)
    {
        $jsonStartPos = strpos($result, '{');
        $jsonString = substr($result, $jsonStartPos);

        return json_decode($jsonString);
    }

    private function sendErrors($website, $data)
    {
        if (count($this->logs_data) >= 1) {
            $errors = array_count_values(array_column($this->logs_data, 'status'))['error'];
            if ($errors >= 1) {

                $subject = 'ERRORS - Shipping(' . $website . ')';
                $blade = 'amz-spapi::errors-shipping-info';
                $process = 'email';
                $this->sendMail($subject, $website, $data, $blade, $process);
            }
        }
    }

}
