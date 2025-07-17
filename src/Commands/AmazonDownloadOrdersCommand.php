<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Typhoeus\JleversSpapi\Models\MongoDB\AmzSettings;
use Typhoeus\JleversSpapi\Models\MongoDB\EmailRecipient;
use Typhoeus\JleversSpapi\Models\MySql\SdItem;
use Typhoeus\JleversSpapi\Models\MySql\SdOrder;
use Typhoeus\JleversSpapi\Models\MySql\SdOrderDate;
use Typhoeus\JleversSpapi\Models\MySql\SvShipVia;
use Typhoeus\JleversSpapi\Services\Feed;
use Typhoeus\JleversSpapi\Services\Order;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class AmazonDownloadOrdersCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    private $logs = '';
    private $logs_data = [];

    //protected $signature    = 'amz-spapi:orders {--s|seller=} {--f|from=} {--t|to=} {--c|fulfillment_channel=}';
    protected $signature    = 'amz-spapi:download:orders {--f|from=} {--t|to=} {--c|fulfillment_channel=}';
    protected $description  = 'Download orders base on date in FROM and TO using SP-API';

    protected $email_notif;
    protected $order;
    protected $spapiService;
    private $shipping_total = 0;
    private $tax_total = 0;

    public function __construct(
        Order $order
    ) {
        parent::__construct();
        $this->order = $order;
    }

    public function handle()
    {
        try {

            //date_default_timezone_set('America/Denver');
            //date_default_timezone_set('America/New_York ');
            date_default_timezone_set('UTC');

            $process_start = Carbon::now()->toDateTimeString();
            $from = $this->option('from');
            $to = $this->option('to');
            $fulfillment_channel = $this->option('fulfillment_channel');
            $fulfillment_channels = [];

            $this->logs .= "Type: Orders\n";
            $this->logs .= "Cmd : " . $this->signature . "\n";

            $created_after_date = Carbon::now();
            $created_after_date->subDays($from);
            $created_before_date = Carbon::now();
            $created_before_date->subDays($to);
            $created_before_date->subMinutes(2);

            if (!is_null($fulfillment_channel)) {
                $fulfillment_channel = strtoupper($fulfillment_channel);
                $fulfillment_channels = explode(',', $fulfillment_channel);
            }

            $order_statuses = ['Unshipped', 'PartiallyShipped', 'Canceled'];

            if (in_array('AFN', $fulfillment_channels)) {
                $order_statuses = ['Unshipped','Pending', 'PartiallyShipped', 'Shipped', 'Canceled'];
            }

            //dd($order_statuses);
            $marketplace_ids = ['ATVPDKIKX0DER'];
            //$created_after = $created_after_date->format('Y-m-d\TH:i:01.000\Z');
            //$created_before = $created_before_date->format('Y-m-d\TH:i:s.000\Z');
            //$last_updated_after = null;
            //$last_updated_before = null;
            $created_after = null;
            $created_before = null;
            $last_updated_after = $created_after_date->format('Y-m-d\TH:i:01.000\Z');
            $last_updated_before = $created_before_date->format('Y-m-d\TH:i:s.000\Z');
            //$order_statuses = null;
            $payment_methods = ['CVS','Other'];
            $buyer_email = null;
            $seller_order_id = null;
            $max_results_per_page = 100;
            $easy_ship_shipment_statuses = null;
            $electronic_invoice_statuses = null;
            $next_token = null;
            $amazon_order_ids = null;
            $actual_fulfillment_supply_source_id = null;
            $is_ispu = null;
            $store_chain_store_id = null;
            $data_elements = null;

            $this->logs .= "From: " . $created_after . "\n";
            $this->logs .= "To  : " . $created_before . "\n";
            
            $website = env('APP_NAME');

            $counter = 1;
            $loop_bool = true;
            $page = 1;

            $sellerConfigForPrice = $this->order->setSellerConfig(true);

            try {

                do {
                    $orders = $this->order->getOrders($marketplace_ids, $created_after, $created_before, $last_updated_after, $last_updated_before, $order_statuses, $fulfillment_channels, $payment_methods, $buyer_email, $seller_order_id, $max_results_per_page, $easy_ship_shipment_statuses, $electronic_invoice_statuses, $next_token, $amazon_order_ids, $actual_fulfillment_supply_source_id, $is_ispu, $store_chain_store_id, $data_elements);
                    $next_token = $orders->getNextToken();

                    if (is_null($next_token)) {
                        $loop_bool = false; // Kill loop
                    }

                    if (!is_null($orders)) {

                        foreach ($orders->getOrders() as $order) {
                            try {
                                $this->logs .= $this->storeOrder($counter, $website, $order);
                            } catch (\Exception $e) {
                                $this->logs .= $e->getMessage();

                                if ($e->getCode() == 429) {
                                    $this->logs_data[] = [
                                        'amazon_order_id' => $order['amazon_order_id'],
                                        'status' => 'error',
                                        'message' => $e->getMessage()
                                    ];
                                    //$loop_bool = false; // Kill loop
                                    sleep(10);
                                }
                            }
                            $counter++;
                        }
                        $page++;
                    }
                    sleep(10);

                } while($loop_bool);

            } catch (\Exception $e) {

                $this->logs_data[] = [
                    'amazon_order_id' => $amazon_order_id ?? 'N/A',
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];

                $data['msg'] = $e->getMessage();

                $this->sendErrors($website, $data);
            }

        } catch (\Exception $e) {

            $this->logs .= "\n".$e->getMessage()."\n";

            $data['msg'] = $e->getMessage();

            $this->sendErrors($website, $data);
        }

        $process_end = Carbon::now()->toDateTimeString();
        
        $this->storeLogs($website, $this->signature, $process_start, $process_end, $this->logs_data);
        $this->logs .= "\nStart:\t$process_start\nEnd:\t$process_end\nCmd:\t" . $this->signature . "\nFrom: " . $created_after . "\nTo  : " . $created_before . "\n";
        $subject_fulfillment_channels = 'MFN & AFN';

        if (count($fulfillment_channels) >= 1) {
            $subject_fulfillment_channels = implode(', ', $fulfillment_channels);
        }

        $subject = $website . ' - Download Orders (' . $subject_fulfillment_channels . ')';
        $blade = 'amz-spapi::download-order';
        $process = 'download-order';
        $data = [
            'signature' => $this->signature,
            'process_start' => $process_start,
            'process_end' => $process_end, 
            'created_after' => $created_after,
            'created_before' => $created_before,
            'fulfillment_channels' => $subject_fulfillment_channels,
            'rows' => $this->logs_data
        ];
        
        $this->info($this->logs);

        $this->sendMail($subject, $website, $data, $blade, $process);
    }

    private function storeOrder($counter, $website, $order)
    {
        #dump($order);
        $log_info = '';
        $order_status_info = '';
        $amazon_order_id = $order['amazon_order_id'];
        $order_status = $order['order_status'];
        #$purchase_date = $order['purchase_date'];
        $purchase_date = Carbon::parse($order['purchase_date']);
        $purchase_date->subHours(7);

        $fulfillment_channel = strtoupper($order['fulfillment_channel']);
        $fulfillment_channel = ($fulfillment_channel == 'AFN')? 'yes' : 'no';

        if (in_array($order_status, ['Canceled', 'Pending'])) {
            $order_status_info = $counter. ') Amazon ID (' . $amazon_order_id . ') ' . $order_status . '. Skipped';
            $log_info .= $order_status_info."\n";
            if ($order_status == 'Canceled') {
                /*$canceled_order_count = OrderCanceled::where('order_id', $amazon_order_id)->count();
                if ($canceled_order_count == 0) {
                    $order_info = SdOrder::where('OrderId', $amazon_order_id)->first();
                    if (!is_null($order_info)) {
                        SdOrder::where('OrderId', $amazon_order_id)->update([ 'ship_status' => 'Void' ]);
                    }
                    OrderCanceled::create([
                        'order_id' => $order['amazon_order_id'],
                        'eclipse_id' => '',
                        'seller_order_id' => $order['seller_order_id'],
                        'purchase_date' => new \MongoDB\BSON\UTCDateTime((new \DateTime($purchase_date))->getTimestamp() * 1000),
                        'fulfillment_channel' => $order['fulfillment_channel'],
                        'ship_service_level' => $order['ship_service_level'],
                        'number_of_items_shipped' => $order['number_of_items_shipped'],
                        'number_of_items_unshipped' => $order['number_of_items_unshipped']
                    ]);
                }*/
                SdOrder::where('EclipsePONumber', $amazon_order_id)->update([ 'ShipStatus' => 'Cancelled' ]);
            }
            return $log_info;
        }

        $order_count = SdOrder::where('OrderId', $amazon_order_id)->count();

        //if (false) {
        if ($order_count >= 1) {
            $this->logs_data[] = [
                'amazon_order_id' => $amazon_order_id,
                'status' => 'success',
                'message' => 'Exist'
            ];
            return $counter.') Amazon ID (' . $amazon_order_id . ') Exist. Skipped ' . $purchase_date->format('Y-m-d\TH:i:s.v').'Z' . "\n";
        } elseif (!is_null($order['replaced_order_id'])) {
            $this->logs_data[] = [
                'amazon_order_id' => $amazon_order_id,
                'status' => 'replacement',
                'message' => $replaced_order_id
            ];
            return $counter.') Amazon ID (' . $amazon_order_id . ') Replacement (' . $order['replaced_order_id'] . '). Skipped' . "\n";
        }

        sleep(4);
        $buyer_info = $this->order->getOrderBuyerInfo($order['amazon_order_id']);
        sleep(4);
        $shipping_address = $this->order->getOrderAddress($order['amazon_order_id']);

        $is_prime = $order['is_prime'];
        $bill_name = $buyer_info['buyer_name'];
        $buyer_email = $buyer_info['buyer_email'];

        $ship_name = $shipping_address['shipping_address']['name'];
        $ship_address_1 = $shipping_address['shipping_address']['address_line1'];
        $ship_address_2 = $shipping_address['shipping_address']['address_line2'];
        $ship_address_3 = $shipping_address['shipping_address']['address_line3'];
        $ship_city = $shipping_address['shipping_address']['city'];
        $ship_state = $shipping_address['shipping_address']['state_or_region'];
        $ship_zip_code = $shipping_address['shipping_address']['postal_code'];
        $phone = $shipping_address['shipping_address']['phone'];
        $country_code = $shipping_address['shipping_address']['country_code'];

        //if (false) {
        if (empty($ship_name) && $fulfillment_channel != 'yes') {
            $this->logs_data[] = [
                'amazon_order_id' => $amazon_order_id,
                'status' => 'error',
                'message' => 'Ship Name is empty'
            ];
            return "$counter) Amazon ID ($amazon_order_id) Ship Name is empty.\n";
        } elseif ((empty($ship_address_1) && empty($ship_address_2) && empty($ship_address_3)) && $fulfillment_channel != 'yes') {
        //} elseif (false) {
            $this->logs_data[] = [
                'amazon_order_id' => $amazon_order_id,
                'status' => 'error',
                'message' => 'Ship Address Line/s is empty'
            ];
            return "$counter) Amazon ID ($amazon_order_id) Ship Address Line/s is empty.\n";
        }

        if (empty($ship_address_1) && empty($ship_address_2) && !empty($ship_address_3)) {
            $ship_address_1 = $ship_address_3;
        }
        
        sleep(3);
        $order_items = $this->order->getOrderItems($order['amazon_order_id'], null, null);//->getPayload()->getOrderItems();
        // set shipping and tax
        $this->setTaxAndShipping($order_items);

        $ship_status = 'New';
        if ($fulfillment_channel == 'yes') {
            $ship_status = 'Shipped';
            $ship_name = 'FBA ' . $amazon_order_id;
            $ship_address_1 = '506 N 200 W Cedar City UT 84720';
        }

        $currency_code = 'USD';
        $currency_amount = 0;

        if (!is_null($order['order_total'])) {
            $currency_code = $order['order_total']['currency_code'];
            $currency_amount = $order['order_total']['amount'];
        }

        $website_ship_method_name = ($is_prime == true)? 'AMZ PRIME' : $order['shipment_service_level_category'];
        $email_add = (!empty($buyer_email))? $buyer_email : $amazon_order_id.''.$website;

        $ship_phone = str_replace('ext. ', 'x', str_replace('+1 ', '', $phone));
        $bill_phone = str_replace('ext. ', 'x', str_replace('+1 ', '', $phone));

        $svshipvia = SvShipVia::where('WebSite', $website)->where('ShipViaDescription', $website_ship_method_name)->first();

        if (is_null($svshipvia)) {
            $this->logs_data[] = [
                'amazon_order_id' => $amazon_order_id,
                'status' => 'error',
                'message' => "Invalid SvShipVia ($website_ship_method_name) Order Status ($order_status)"
            ];
            return "$counter) Amazon ID ($amazon_order_id) Invalid SvShipVia ($website_ship_method_name) Order Status ($order_status). Skipped\n";
        }

        $ship_method = $svshipvia->ShipViaShortEclipse;
        $delivery_date = Carbon::parse($order['latest_delivery_date']);
        $delivery_date->subHours(7);

        // Item storing
        $this->storeOrderItems($order_items, $amazon_order_id, $website);

        $grand_total_cost = $this->getGrandTotal($currency_amount, $currency_code);
        $fee_total = 0.001;
        $tax = $this->getTaxTotal();
        $ship_amount = $this->getShippingTotal();
        $discount = 0;
        $fee_schedule = Carbon::now();
        $fee_schedule->addHours(4);

        $data = [
            'InEclipse' => 'no',
            'EclipseId' => '',
            'EclipsePONumber' => $amazon_order_id,
            'OrderId' => $amazon_order_id,

            'ShipStatus' => $ship_status,
            'Website' => $website,
            'WebsiteShipMethodName' => $website_ship_method_name,
            'CustomerId' => $email_add,

            'ShipName' => $ship_name,
            'ShipAddress1' => $ship_address_1,
            'ShipAddress2' => $ship_address_2,
            'ShipCity' => $ship_city,
            'ShipState' => $ship_state,
            'ShipZip' => $ship_zip_code,
            'ShipCountry' => $country_code,
            'ShipPhone' => $ship_phone,
            'ShipEmail' => $email_add,
            'ShipMethod' => $ship_method,

            'BillName' => $bill_name,
            'BillCountry' => $country_code,
            'BillEmail' => $email_add,
            'BillPhone' => $ship_phone,

            'OrderDate' => $purchase_date->format('Y-m-d\TH:i:s.v').'Z',
            'Tax' => $tax,
            'Discount' => $discount,
            'GrandTotalCost' => $grand_total_cost,
            'ShippingAmount' => $ship_amount,
            'AmazonFee' => $fee_total,
            'RequiredDate' => Carbon::parse($order['latest_delivery_date'])->toDateTime(),
            'DeliveryDate' => $delivery_date->toDateTime(),
            'AmazonFulfilled' => $fulfillment_channel
        ];
        //dump($data);
        SdOrder::create($data);
        SdOrderDate::create([
            'order_id' => $amazon_order_id,
            'earliest_ship_date' => Carbon::parse($order['earliest_ship_date'])->toDateTime(),
            'latest_ship_date' => Carbon::parse($order['latest_ship_date'])->toDateTime(),
            'earliest_delivery_date' => Carbon::parse($order['earliest_delivery_date'])->toDateTime(),
            'latest_delivery_date' => Carbon::parse($order['latest_delivery_date'])->toDateTime(),
        ]);

        $ngp_swift_flag_delete = false;

        if ($website == 'www.swiftgarden.com' && $ngp_swift_flag_delete) {
            SdOrder::where('OrderId', $amazon_order_id)->delete();
            SDItem::where('OrderId', $amazon_order_id)->delete();
        }

        sleep(12);

        $this->logs_data[] = [
            'amazon_order_id' => $amazon_order_id,
            'status' => 'success',
            'message' => $purchase_date->format('Y-m-d\TH:i:s.v').'Z'
        ];
        return $counter. ') Amazon ID (' . $amazon_order_id . ') Inserted! - ' . $purchase_date->format('Y-m-d\TH:i:s.v').'Z' ."\n";
    }

    public function storeOrderItems($order_items, $amazon_order_id, $website)
    {
        $total_item_price = 0;
        foreach ($order_items['order_items'] as $item) {

            $order_item_id = $item['order_item_id'];
            $itemCount = SDItem::where('WebSite', $website)->where('EclipsePONumber', $amazon_order_id)->where('OrderItemId', $order_item_id)->count();
            
            if ($itemCount == 0) {
            ///if (true) {
                $sku = preg_replace("/[^0-9]/", '', $item['seller_sku']);
                #dump($sku);
                $qty = intval(e($item['quantity_ordered']));
                $unit_extended_cost = 0;

                if (!is_null($item['item_price'])) {
                    $unit_extended_cost = floatval($item['item_price']['amount']);
                }

                if ($qty > 0) {
                    $unit_cost = ($unit_extended_cost / $qty);
                } else {
                    $unit_cost = $unit_extended_cost;
                }

                if ($website == 'www.swiftgarden.com') {
                    $ngp_row = SDNgpId::where('sku', $sku)->first();
                    if (is_null($ngp_row)) {
                        $ngp_swift_flag_delete = true;
                        $ngp_compact = compact('website', 'amazon_order_id', 'sku');
                        $subject = $website.' ('.$amazon_order_id.') - NGP - No Eclipse ID';
                        $process = 'order-download-ngpswift';
                        $blade = 'amz-spapi::report';
                        $this->sendMailNgpSwift($subject, $website, $ngp_compact, $blade, $process);
                    } else {
                        $sku = $ngp_row->eclipseId;
                    }
                }
                
                $data_item = [
                    'EclipseId' => '',
                    'OrderId' => $amazon_order_id,
                    'OrderItemId' => $order_item_id,
                    'EclipseProductId' => $sku,
                    'WebProductId' => $sku,
                    'Sku' => $sku,
                    'ProductName' => utf8_decode($item['title']),
                    'QuantityOrdered' => intval(e($item['quantity_ordered'])),
                    'QuantityShipped' => 0,
                    'UnitCost' => $unit_cost,
                    'UnitExtendedCost' => $unit_extended_cost,
                    'UnitWeight' => 0,
                    'WebSite' => $website,
                    'EclipsePONumber' => $amazon_order_id
                ];
                $total_item_price += $unit_extended_cost;
                //dump($data_item);
                SDItem::create($data_item);
            }
        }
    }

    public function setTaxAndShipping($order_items)
    {
        foreach ($order_items['order_items'] as $item) {
            $item_tax_amount = 0;
            $shipping_tax = 0;
            
            if (!is_null($item['item_tax'])) {
                $item_tax_amount = floatval(e($item['item_tax']['amount']));
            }

            if (!is_null($item['shipping_tax'])) {
                $shipping_tax = floatval(e($item['shipping_tax']));
            }

            if (!is_null($item['shipping_price'])) {
                $this->shipping_total += floatval(e($item['shipping_price']['amount']));
            }
            
            $this->tax_total += floatval($item_tax_amount);
        }
    }

    public function getGrandTotal($amount, $currency_code)
    {
        $amount = floatval($amount);
        $rate = $this->getConversionRate($currency_code);
        return floatval($amount * $rate);
    }

    public function getTaxTotal()
    {
        return $this->tax_total;
    }

    public function getShippingTotal()
    {
        return $this->shipping_total;
    }

    public function getConversionRate($currency_code)
    {
        $settings = AmzSettings::where('type', 'order-download-conversion-rate')->get();

        if ($currency_code == 'USD' || empty($currency_code)) {
            return $settings->where('name', 'USD')->first()->value; //1.0;
        } elseif ($currency_code == 'CAD') {
            return $settings->where('name', 'CAD')->first()->value; //0.78;
        }

        $currency_converter_api_key = $settings->where('name', 'currency_converter_api_key')->first()->value;

        $other_currency = $this->getConversionRateRequest($currency_code, 'USD', $currency_converter_api_key);

        return $other_currency;
    }

    public function sendMailNgpSwift($subject, $website, $data, $blade, $process)
    {
        $emails = EmailRecipient::where('process', $process)->get();
        $emails->where('type', 'to')->pluck('email')->toArray();
        $email_cc = $emails->where('type', 'cc')->pluck('email')->toArray();

        Mail::send($blade, $data, function ($m) use ($subject, $website, $email_to, $email_cc) {
            $m->to($email_to);
            $m->cc($email_cc);
            $m->from('amazon_spapi@plumbersstock.com', $website);
            $m->subject($subject);
        });
    }

    private function sendErrors($website, $data)
    {
        $subject = 'ERRORS - Order(' . $website . ')';
        $blade = 'amz-spapi::report';
        $process = 'order-download';
        $this->sendMail($subject, $website, $data, $blade, $process);
    }
}
