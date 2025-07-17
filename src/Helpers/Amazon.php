<?php

namespace Typhoeus\JleversSpapi\Helpers;

use \Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SellingPartnerApi\Configuration;
use SellingPartnerApi\Endpoint;
use Typhoeus\JleversSpapi\Models\MongoDB\Seller;

class Amazon
{
    protected $mws_url = 'https://mws.amazonservices.com';
    protected $version;

    protected $signature_method;
    protected $signature_version;
    protected $mws_auth_token;
    protected $amazon_access_key_id;
    protected $amazon_access_secret;
    protected $amazon_merchant_id;
    protected $amazon_marketplace_id;
    protected $created_after;
    protected $timestamp;

    protected $currency_converter_url = 'http://api.currencyconverterapi.com/api/v5/convert';

    protected $shipping_total = 0;
    protected $tax_total = 0;

    /*public function __construct($seller)
    {
        $row = Seller::whereSellerId($seller)->first();
        return $row;
    }*/

    public function checkSeller($seller)
    {
        $row = Seller::whereSellerId($seller)->first();
        return $row;
    }

    public function sendMail($subject, $website, $msg, $blade, $process)
    {
        $emails = EmailRecipient::where('process', $process)->get();
        $email_to = $emails->where('type', 'to')->pluck('email')->toArray();
        $email_cc = $emails->where('type', 'cc')->pluck('email')->toArray();

        Mail::send($blade, compact('subject', 'website', 'msg'), function ($m) use ($subject, $website, $email_to, $email_cc) {
            $m->to($email_to);
            $m->cc($email_cc);
            $m->from('amazon_spapi@plumbersstock.com', $website);
            $m->subject($subject);
        });
    }

    public function setConfig($seller_info)
    {
        $configuration = new Configuration([
            'lwaClientId'        => $seller_info->app_client_id,
            'lwaClientSecret'    => $seller_info->app_client_secret,
            'lwaRefreshToken'    => $seller_info->refresh_token,
            'awsAccessKeyId'     => $seller_info->access_key,
            'awsSecretAccessKey' => $seller_info->secret_key,
            'roleArn'            => $seller_info->role_arn,
            'endpoint'           => Endpoint::NA
        ]);
        return $configuration;
    }


    /*
            Reserved code!!!!!!!!!
    */


    private function setPutLIstingSchema($row = null, $seller_info = null)
    {
        $product = TyphoeusProduct::where('amazon.'.$row->seller.'.skus.sku', $row->sku)
        ->first(['productId', 'features', 'amazon', 'dimensions', 'priceLine', 'brand', 'mpn', 'upc', 'xologic_data', 'inventory', 'keywords']);

        $channel_inventory = $seller_info->channel_inventory['kw'];

        if ($row->seller == $product->website) {
            $channel_inventory = $seller_info->channel_inventory['po'];
        }

        #dd($product->xologic_data['manufacturer']);
        $productType = 'PLUMBING_FIXTURE';
        $language_tag = 'en_US';

        $marketplace_id = $seller_info->marketplace_id;
        $item_name = $row->item_name;
        $product_description = $row->item_description;
        $supplier_declared_dg_hz_regulation = 'not_applicable';
        $batteries_required = false;
        $merchant_suggested_asin = $row->asin;
        $condition_type = 'new_new';
        $brand = $product->amazon['manufacturer']['content']['attributes']['Brand'] ?? $product->brand;
        $model_number = $product->mpn;
        $model_name = $product->mpn;
        $country_of_origin = 'US';
        $manufacturer = $product->amazon['content']['attributes']['Manufacturer'] ?? $product->xologic_data['manufacturer'];

        $item_package_weight = $product->amazon['content']['attributes']['PackageDimensions']['Weight'] ?? $product->xologic_data['dimensions']['weight'];
        $item_package_weight_unit = 'pounds';
        $included_components = 'n/a';
        $bullet_points = [];

        if (isset($product->features)) {
            foreach ($product->features as $key => $value) {
                if (count($bullet_points) > 9) {
                    break;
                }
                $bullet_points[] = [ 'value' => $value ];
            }
        }

        $number_of_items = $product->amazon['content']['attributes']['NumberOfItems'] ?? 1;
        $list_price = $product->inventory['availability'][$channel_inventory]['price'];
        $color = $product->amazon['content']['attributes']['Color'] ?? '';
        $item_type_keyword = $product->keywords;

        #dump($number_of_items);
        #dd($item_type_keyword);

        $item_dimensions_width_unit = 'inches';
        $item_dimensions_length_unit = 'inches';
        $item_dimensions_height_unit = 'inches';
        $item_dimensions_weight_unit = 'pounds';

        $item_package_dimensions_width_unit = 'inches';
        $item_package_dimensions_length_unit = 'inches';
        $item_package_dimensions_height_unit = 'inches';
        $item_package_dimensions_weight_unit = 'pounds';

        if (isset($product->amazon['content']['attributes']['ItemDimensions'])) {
            $item_dimensions_width_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Width'];
            $item_dimensions_length_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Length'];
            $item_dimensions_height_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Height'];
            $item_dimensions_weight_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Weight'];

            $item_package_dimensions_width_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Width'];
            $item_package_dimensions_length_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Length'];
            $item_package_dimensions_height_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Height'];
            $item_package_dimensions_weight_value = (double)$product->amazon['content']['attributes']['ItemDimensions']['Weight'];
        } else {
            $item_dimensions_width_value =  (double)$product->dimensions['width'];
            $item_dimensions_length_value = (double)$product->dimensions['length'];
            $item_dimensions_height_value = (double)$product->dimensions['height'];
            $item_dimensions_weight_value = (double)$product->dimensions['weight'];

            $item_package_dimensions_width_value =  (double)$product->dimensions['width'];
            $item_package_dimensions_length_value = (double)$product->dimensions['length'];
            $item_package_dimensions_height_value = (double)$product->dimensions['height'];
            $item_package_dimensions_weight_value = (double)$product->dimensions['weight'];
        }

        $number_of_boxes = 1;

        $schema = [
            'sku' => $row->sku,
            'productType' => $productType,
            'attributes' => [
                'item_name' => [
                    [
                        'value' => $item_name,
                        'language_tag' => $language_tag,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'product_description' => [
                    [
                        'value' => $product_description,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'supplier_declared_dg_hz_regulation' => [
                    [
                        'value' => $supplier_declared_dg_hz_regulation,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'batteries_required' => [
                    [
                        'value' => $batteries_required,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'merchant_suggested_asin' => [
                    [
                        'value' => $merchant_suggested_asin,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'condition_type' => [
                    [
                        'value' => $condition_type,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'brand' => [
                    [
                        'value' => $brand,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'model_number' => [
                    [
                        'value' => $model_number,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'model_name' => [
                    [
                        'value' => $model_name,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'country_of_origin' => [
                    [
                        'value' => $country_of_origin,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'manufacturer' => [
                    [
                        'value' => $manufacturer,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'item_type_keyword' => [
                    [
                        'value' => $item_type_keyword,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'item_package_weight' => [
                    [
                        'unit' => $item_package_weight_unit,
                        'value' => $item_package_weight,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'included_components' => [
                    [
                        'value' => $included_components,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'list_price' => [
                    [
                        'value' => $list_price,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'number_of_items' => [
                    [
                        'value' => $number_of_items,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'number_of_boxes' => [
                    [
                        'value' => $number_of_boxes,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'item_dimensions' => [
                    [
                        'width' => [
                            'unit' => $item_dimensions_width_unit,
                            'value' => $item_dimensions_width_value
                        ],
                        'length' => [
                            'unit' => $item_dimensions_length_unit,
                            'value' => $item_dimensions_length_value
                        ],
                        'height' => [
                            'unit' => $item_dimensions_height_unit,
                            'value' => $item_dimensions_height_value
                        ],
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'bullet_point' => $bullet_points,
                'color' => [
                    [
                        'value' => $color,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'item_package_dimensions' => [
                    [
                        'width' => [
                            'unit' => $item_package_dimensions_width_unit,
                            'value' => $item_package_dimensions_width_value
                        ],
                        'length' => [
                            'unit' => $item_package_dimensions_length_unit,
                            'value' => $item_package_dimensions_length_value
                        ],
                        'height' => [
                            'unit' => $item_package_dimensions_height_unit,
                            'value' => $item_package_dimensions_height_value
                        ],
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
            ]
        ];

        return json_encode($schema);
    }


    public function getItems($request, $amazon_order_id, $next_token)
    {
        $item_results = $request->getOrderItems($request, $amazon_order_id, $next_token);
        $items = $item_results->getPayload()->getOrderItems();

        return $items;
    }

    public function getFinances($version, $amazon_order_id)
    {
        $url = $this->mws_url . '/Finances/' . $version;
        $other_fields = [
            'Action' => 'ListFinancialEvents',
            'AmazonOrderId' => $amazon_order_id,
            'FinancialEventGroupId' => $amazon_order_id,
            'Version' => $version
        ];

        $request = $this->sendRequest($url, $other_fields);
        $response = simplexml_load_string($request->getBody()->getContents());

        return $response;
    }

    public function getFeeTotal($finances, $amount, $currency_code, $grand_total)
    {
        $item_list = [];
        if (!empty($finances['financial_events']['shipment_event_list'])) {
            #$item_list = $finances['financial_events']['shipment_event_list'][0]['shipment_item_list'];
        }

        $fee_total = 0;
        foreach ($item_list as $item) {
            $fee_list = $item['item_fee_list'];
            foreach ($fee_list as $fee) {
                $fee_total += abs((double)$fee['fee_amount']['currency_amount']);
            }
        }

        if ($fee_total == 0) {
            //the fee is %15 of the grand total - taxes
            //just rounding up to the nearest penny
            #$grand_total = $this->getGrandTotal($amount, $currency_code);
            #$tax_total = $this->getTaxTotal();
            $tax_total = 0;
            #dump($tax_total);
            $amazon_fee_percent = 0.15;
            $feeable_total = $grand_total - $tax_total;

            $fee_total = round( $feeable_total * $amazon_fee_percent, 2, PHP_ROUND_HALF_UP);

            if($fee_total < 1) {
                $fee_total = 1.00;
            }
        }

        return $fee_total;
    }

    public function getGrandTotal($amount, $currency_code)
    {
        $amount = floatval($amount);
        $rate = $this->getConversionRate($currency_code);
        return floatval($amount * $rate);
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

    public function getConversionRateRequest($from, $to, $currency_converter_api_key)
    {
        $value_key = $from . '_' . $to;
        $url = $this->currency_converter_url . '?compact=ultra&q=' . $value_key . '&apiKey=' . $currency_converter_api_key;
        $request = $this->guzzle_request('GET', $url, []);

        $response = json_decode($request->getBody()->getContents());

        return $response->$value_key;
    }

    public function setTaxAndShipping($items)
    {
        $this->tax_total = 0;
        foreach ($items as $item) {
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

    public function guzzle_request($method, $url, $options)
    {
        try {
            $client = new Client();
            $request = $client->request($method, $url, $options);
            return $request;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $e->getResponse();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $e->getResponse();
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            dump($e->getResponse()->getBody()->getContents());
            dump('Sleep 120 seconds...');
            sleep(120);
            return $this->guzzle_request($method, $url, $options);
        }
    }

    public function getTaxTotal()
    {
        return $this->tax_total;
    }

    public function getShippingTotal()
    {
        return $this->shipping_total;
    }

    public function getSignature($url, $parameters, $secret_key, $feed = false)
    {
        if($feed) {
            $string_to_sign = $this->calculateStringToSignatureFeed($url, $parameters);
        }
        else {
            $string_to_sign = $this->calculateStringToSignature($url, $parameters);
        }

        return base64_encode(hash_hmac('sha256', $string_to_sign, $secret_key, true));
    }

    public function calculateStringToSignature($url, $parameters, $method = "POST")
    {
        $url = parse_url($url);
        $string = "$method\n";
        $string .= $url['host']."\n";
        $string .= $url['path']."\n";
        $string .= $this->getParametersAsString($parameters);
        return $string;
    }

    public function calculateStringToSignatureFeed($url, $parameters, $method = "POST")
    {
        //use this only for feed submissions
        $url = parse_url($url);
        $string = "$method\n";

        /*$string .= $url['host'];
        $string .= "\n/\n";
        $string .= array_key_exists('path', $url) ? $url['path'] : null;*/

        $string .= $url['host']."\n";
        $string .= array_key_exists('path', $url) ? $url['path']."\n" : null;

        $string .= $this->getParametersAsString($parameters);
        return $string;
    }

    public function getParametersAsString($parameters)
    {
        uksort($parameters, 'strcmp');

        $queryParameters = [];

        foreach ($parameters as $key => $value) {
            $key = rawurlencode($key);
            $value = rawurlencode($value);

            $queryParameters[] = sprintf('%s=%s', $key, $value);
        }

        return implode('&', $queryParameters);
    }

    public function slackLogging($website, $type, $log_info)
    {
        $loggging = SlackLogDetail::where('website', $website)->where('type', $type)->first();

        if (!is_null($loggging)) {
            Log::channel($loggging->channel)->info($log_info);
        } else {
            Log::channel('slackAmzOrderLogs')->info($log_info);
        }
    }

    public function slackReporting($channel, $website, $log_info)
    {
        if ($log_info != '') {
            Log::channel($channel)->info("$website\n$log_info");
        }
    }

    public function floorp($val, $precision)
    {
        $mult = pow(10, $precision); // Can be cached in lookup table
        return floor($val * $mult) / $mult;
    }

    public function shippingReport($website, $blade, $process, $subject, $data)
    {
        $emails = EmailRecipient::where('process', $process)->get();
        $email_to = $emails->where('type', 'to')->pluck('email')->toArray();
        $email_cc = $emails->where('type', 'cc')->pluck('email')->toArray();

        Mail::send($blade, $data, function ($m) use ($subject, $website, $email_to, $email_cc) {
            $m->to($email_to);
            $m->cc($email_cc);
            $m->from('amazon_spapi@plumbersstock.com', $website);
            $m->subject($subject);
        });
    }

    public function storeSuccess($website, $order_id, $process, $message)
    {
        $row = DataLog::create([
            'process' => $process,
            'website' => $website,
            'order_id' => $order_id,
            'message' => $message,
        ]);
    }

    public function catchError($website, $order_id, $error, $blade, $process, $subject)
    {
        $row = DataLog::create([
            'process' => $process,
            'website' => $website,
            'order_id' => $order_id,
            'error' => (string)$error,
        ]);

        $message = $error->getMessage();

        $this->sendMail($subject, $website, $message, $blade, $process);
    }

    public function storeError($website, $order_id, $error, $blade, $process, $subject)
    {
        $row = DataLog::create([
            'process' => $process,
            'website' => $website,
            'order_id' => $order_id,
            'error' => (string)$error,
        ]);

        try {
            $message = $error->getMessage();
        } catch (\Exception $e) {
            $errorJson = json_decode($error->getResponseBody());
            $message = $errorJson->errors[0]->message;
        }
        /*if (!is_null($error->getResponseBody())) {
            $errorJson = json_decode($error->getResponseBody());
            $message = $errorJson->errors[0]->message;
        } else {
            $message = $error->getMessage();
        }*/

        $this->sendMailError($subject, $website, $message, $blade);
    }

    public function sendMailError($subject, $website, $msg, $blade)
    {
        $emails = EmailRecipient::where('process', 'error')->get();
        $email_to = $emails->where('type', 'to')->pluck('email')->toArray();
        $email_cc = $emails->where('type', 'cc')->pluck('email')->toArray();

        Mail::send($blade, compact('subject', 'website', 'msg'), function ($m) use ($subject, $website, $email_to, $email_cc) {
            $m->to($email_to);
            $m->cc($email_cc);
            $m->from('amazon_spapi@plumbersstock.com', $website);
            $m->subject($subject);
        });
    }

    public function sendMailNgpSwift($subject, $website, $data, $blade, $process)
    {
        $emails = EmailRecipient::where('process', $process)->get();
        $email_to = $emails->where('type', 'to')->pluck('email')->toArray();
        $email_cc = $emails->where('type', 'cc')->pluck('email')->toArray();

        Mail::send($blade, $data, function ($m) use ($subject, $website, $email_to, $email_cc) {
            $m->to($email_to);
            $m->cc($email_cc);
            $m->from('amazon_spapi@plumbersstock.com', $website);
            $m->subject($subject);
        });
    }

    public function checkDeadstock($product) {
		if($product->isDeadstock()) {
			if((strpos(strtolower($product->getDeadstock()),"unload") !== false) && ($product->getAvailability() <= 1)) { //if we are just unloading, stop at 1 in stock
				return false;
			}
			else {
				return $product->getDeadstock();
			}
		}
		else {
			return false;
		}
	}

    public function isMap($product) {
		$mapMethod = $product->getMapMethod();
		$mapPrice = $product->getMapPrice();

		if(!empty($mapMethod) && !empty($mapPrice)) {
			return true;
		}
		else {
			return false;
		}
	}
}
