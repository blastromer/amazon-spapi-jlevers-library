<?php

namespace Typhoeus\JleversSpapi\Services;

use SellingPartnerApi\Model\ListingsV20210801\ListingsItemPatchRequest;
use SellingPartnerApi\Model\ListingsV20210801\ListingsItemPutRequest;
use SellingPartnerApi\Model\ListingsV20210801\PatchOperation;
use SellingPartnerApi\Model\ListingsV20210801\ListingsItemPatch;
use Typhoeus\JleversSpapi\Http\Request;
use Typhoeus\JleversSpapi\Models\MongoDB\TyphoeusProduct;

class Listing extends SpapiService
{
    /**
     * @param array $skus
     *
     * @return bool|array
     */
    public function getItemsBySKU(array $skus)
    {
        $items = [];

        if (is_array($skus)) {
            foreach ($skus as $sku) {
                $clnSKU     = $this->app->dataHelper->sanitizeData($sku);
                $data       = $this->getItemBySKU($clnSKU);
                $items[]    = $data;
            }
            return $items ?? [];
        }
        return false;
    }

    /**
     * @param string $sku
     *
     * @return array
     */
    public function getItemBySKU(string $sku)
    {
        $request        = new Request($this->seller->configurations(), $this->getListingMethod());
        $productList    = $request->getItem($sku);

        return $productList;
        // return $this->app->dataHelper->aggregateData($productList);
    }

    /**
     * @param string $sku
     *
     * @return array
     */
    public function getProductDetails(string $sku, $productType)
    {
        $request        = new Request($this->seller->configurations(), $this->getListingMethod());
        $productList    = $request->getItemDetails($sku, $productType);
        return $productList ?? [];
    }

    public function patchItem($sku, $attr = 'fulfillment_availability', $value = [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 0]], $productType = null)
    {
        $sellerConfig   = $this->seller->configurations();
        if (is_null($productType)) {
            $productType    = $this->getProductDetails($sku, 'product_type') ?? null;
        }
        if (isset($productType['error'])) {
            $response = ['error' => ['message' => "Not found..."]];
			return $response;
        }
        $body = new ListingsItemPatchRequest([
            "messageId"     => 1,
            "operationType" => "PATCH",
            'product_type'  => $productType,
            'patches' => [
                new PatchOperation([
                    'op'    => 'replace',
                    'path'  => '/attributes/' .  $attr,
                    'value' => $value
                ])
            ]
        ]);
        $sellerId       = $sellerConfig['merchant_id'] ?? $sellerConfig['amazon_merchant_id'];
        $sku            = $sku;
        $marketplaceIds = [$sellerConfig['marketplace_id']]; // Example marketplace ID for Amazon.com
        $issueLocale    = 'en_US';

        try {
            $request    = new Request($this->seller->configurations(), $this->getListingMethod());
            $response   = $request->getAPIinstance()->patchListingsItem($sellerId, $sku, $marketplaceIds, $body, $issueLocale);
            return $response ?? [];
		} catch (\Exception $e) {
			$response = ['error' => ['message' => $e->getMessage()]];
			return $response;
		}
    }

    public function patchItems($attr = 'fulfillment_availability', array $itemAttr = [])
    {
        $reports = [];
        foreach ($itemAttr as $sku => $value) {
            $reports[$sku] = $this->patchItem($sku, $attr, $value);
        }
        return $reports;
    }

    public function patchListingsItemAvailability() {
        $sellerConfig   = $this->seller->configurations();
        $sellerId = $sellerConfig['merchant_id'] ?? $sellerConfig['amazon_merchant_id'];
        $sku = "833111";
        $marketplaceIds = [$sellerConfig['marketplace_id']]; // Replace with your marketplace ID

        // Prepare the Patch Request
        $body = new ListingsItemPatchRequest([
            "message_id"     => 1,
            "operation_type" => "PATCH",
            "product_type" => $this->getProductDetails($sku, 'product_type'),
            "patches" => [
                new PatchOperation([
                    "op" => "replace",
                    "path" => "/attributes/fulfillment_availability",
                    "value" => [
                        [
                            "fulfillment_channel_code" => "DEFAULT",
                            "quantity" => 1,
                            "lead_time_to_ship_max_days" => 4
                        ]
                    ]
                ])
            ]
        ]);
        $issueLocale    = 'en_US';
        // Send the PATCH request
        try {
            $request    = new Request($this->seller->configurations(), $this->getListingMethod());
            $response   = $request->getAPIinstance()->patchListingsItem($sellerId, $sku, $marketplaceIds, $body, $issueLocale);
            return $response ?? [];
        } catch (Exception $e) {
            echo "Exception when calling ListingsV20210801Api->patchListingsItem: ", $e->getMessage(), PHP_EOL;
        }
    }

    public function putListing($row, $seller_info, $issueLocale)
    {
        $marketplaceIds = [$seller_info->marketplace_id];
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
        $hazmat = '0';
        $batteries_required = false;
        $merchant_suggested_asin = $row->asin;
        $condition_type = 'new_new';
        $merchant_shipping_group = $row->merchant_shipping_group;
        $brand = $product->amazon['manufacturer']['content']['attributes']['Brand'] ?? $product->brand;
        $model_number = $product->mpn;
        $model_name = $product->mpn;
        $part_number = $product->mpn;
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

        $body = new ListingsItemPutRequest([
            'product_type' => 'TOOLS',
            'requirements' => 'LISTING',
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
                'hazmat' => [
                    [
                        'value' => $hazmat,
                        'aspect' => 'transportation_regulatory_class',
                        'marketplace_id' => $marketplace_id
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
                        'currency' => 'USD',
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
                'color' => [
                    [
                        'value' => $color,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'part_number' => [
                    [
                        'value' => $part_number,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'merchant_shipping_group' => [
                    [
                        'value' => $merchant_shipping_group,
                        'marketplace_id' => $marketplace_id,
                    ]
                ],
                'bullet_point' => $bullet_points,
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
                /*'style' => [
                    [
                      'language_tag' => 'en_US',
                      'value' => $style,
                      'marketplace_id' => $marketplace_id
                    ]
                ],*/

                /*'purchasable_offer' => [
                    [
                        'audience'  => 'ALL',
                        'currency'  => 'USD',
                        'our_price' => [
                            [
                                'schedule' => [
                                    [
                                        'value_with_tax' => (float) $purchasable_offer
                                    ]
                                ]
                            ]
                        ],
                        'marketplace_id' => $marketplaceIds
                    ]
                ]*/

                /*'fulfillment_availability' => [
                    [
                        'fulfillment_channel_code' => 'DEFAULT',
                        'quantity' => 0,
                        'lead_time_to_ship_max_days' => 3,
                    ]
                ],*/

                /*'offers' => [
                    [
                        'marketplace_id'    => $marketplaceIds,
                        'offer_type' => $offer_type,//'B2C',
                        'price' => [
                            'amount' => (float) $offers,
                            "is_fulfilled_by_amazon"    => false
                        ]
                    ]
                ]*/

            ],
        ]);

        try {
            $sellerId = $seller_info->amazon_merchant_id;
            $sku = $row->sku;
            $marketplaceIds = [$marketplace_id]; // Example marketplace ID for Amazon.com
            //$issueLocale = 'en_US';
            $request    = new Request($this->seller->configurations(), $this->getListingMethod());
            $response   = $request->getAPIinstance()->putListingsItem($sellerId, $sku, $marketplaceIds, $body, $issueLocale);
            return $response ?? [];
        } catch (Exception $e) {
            return $e;
            //return 'Exception when calling ListingsItemsApi->patchListingsItem: ' . $e->getMessage(), PHP_EOL;
        }
    }

    public function uploadListing($sku, array $productData, array $offersData, $sellerId = 'A2G5859HCU1M8W', $marketplaceIds = ['ATVPDKIKX0DER'])
    {
        $body = new ListingsItemPutRequest([
            'product_type' => $productData['product_type'] ?? 'TOOLS',
            'requirements' => $productData['requirements'] ?? 'LISTING',
            'attributes'   => $productData['attributes'] ?? [],
            'offers'       => $offersData ?? [],
        ]);

        try {
            $issueLocale = 'en_US';
            $request = new Request($this->seller->configurations(), $this->getListingMethod());
            $response = $request->getAPIinstance()->putListingsItem($sellerId, $sku, $marketplaceIds, $body, $issueLocale);
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]];
            return $response;
        }
    }

    public function getItem($sellerId, $sku, $marketplaceId, $issueLocale = 'en_US', $includedData)
    {
        $request = new Request($this->seller->configurations(), $this->getListingMethod());
        try {
            $response = $request->getAPIinstance()->getListingsItem(
                $sellerId,
                $sku,
                $marketplaceId,
                $issueLocale,
                $includedData
            );
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]];
            return $response;
        }
    }
}