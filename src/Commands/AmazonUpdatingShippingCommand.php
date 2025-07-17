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

class AmazonUpdatingShippingCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi:update:shipping';
    protected $description  = 'Catalog item collation command.';
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
            $now->subDays(15);


            $rows = SdOrder::where('WebSite', $website)->whereRaw('OrderDate>DATE_SUB(NOW(), INTERVAL 14 DAY)')
                    ->whereIn('EclipseId', function($query) use ($now){
                        $query->select('EclipseId')->from('sd_shipments')->where('ShipStatus', 'Pending')->where('created_at', '>', $now->format('Ymd'))->where('TrackingNumber', '<>', '');
                    })
                    ->limit(100)->get();
            //$rows = SdOrder::where('EclipseId', 'S4928077')->limit(100)->get();

            foreach ($rows as $row) {
                try {
                    $this->log_info .= $row->OrderId . "\n";
                    $order_id = $row->OrderId;
                    $eclipse_id = $row->EclipseId;

                    $shipments = SdShipment::where('EclipseId', 'LIKE', $row->EclipseId . '%')
                                 ->get(['*', DB::raw('(SELECT OrderItemId FROM sd_items WHERE Sku=ProductId AND EclipseId=\''.$row->EclipseId.'\' ORDER BY OrderItemId DESC LIMIT 1) AS OrderItemId')]);

                    foreach ($shipments as $shipment) {
                        dd($shipment);
                        try {
                            $order_item_id = $shipment->OrderItemId;

                            if (is_null($order_item_id)) {// sd_items sku is not the same in sd_shipments because it is a KIT product
                                $item = SdItem::where('EclipseId', $row->EclipseId)->where('Sku', '<>', $shipment->ProductId)->first();
                                $order_item_id = $item->OrderItemId;
                                $this->log_info .= 'Sku ' . $item->Sku . ' ';
                            } else {
                                $item = SdItem::where('EclipseId', $row->EclipseId)->where('Sku', $shipment->ProductId)->first();
                                //$order_qty = $shipment->Qty;
                                $this->log_info .= 'Sku ' . $shipment->ProductId . ' ';
                            }

                            $order_qty = $item->QuantityOrdered;
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
                                            'orderItemId' => $order_item_id,
                                            'quantity' => $order_qty
                                        ]
                                    ]
                                ],
                                'marketplaceId' => 'ATVPDKIKX0DER'
                            ];
                            // dump($payload);dump($shipment->EclipseId);dd($shipment->cartonId);
                            SdShipment::where('EclipseId', $shipment->EclipseId)->where('cartonId', $shipment->cartonId)->update(['ShipStatus' => 'Processed']);
                            // return null if success
                            $response = $this->order->confirmShipment($order_id, $payload);
                            $this->log_info .= " Success \n";
                            $this->logs_data[] = [
                                'amazon_order_id' => $order_id,
                                'eclipse_id' => $eclipse_id,
                                'status' => 'success',
                                'message' => $response
                            ];
                            //dump($response);
                            sleep(1);
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
                    }

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
            }

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

        $this->sendMail($subject, $website, $data, $blade, $process);
        $this->sendErrors($website, $data);
        $this->info($this->log_info);
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
