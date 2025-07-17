<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MongoDB\ProductAttribute;
use Typhoeus\JleversSpapi\Models\MongoDB\CatalogItems;
use Typhoeus\JleversSpapi\Models\MongoDB\CatalogItemAsin;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MySql\AmazonAsinProductType;
use Typhoeus\JleversSpapi\Models\MongoDB\Logs\ThrottlingListingsError;
use Typhoeus\JleversSpapi\Models\MongoDB\Logs\ProcessingListingError;
use Illuminate\Support\Facades\Storage;

class PutListingCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:upload:listings
                            {--type=        : This option is required and must be either SINGLE or BATCH}
                            {--category=    : This option is required and must be either NEW or EXISTING}
                            {--notification : This option is not required. If included, an email notification and report will be sent}
                            ';
    protected $description = 'This command will patch or change the specific field';

    protected $listing;
    protected $products;

    public $types               = ['SINGLE', 'BATCH'];
    public $categories          = ['NEW', 'EXISTING'];
    public $vendorInitial = [
        'po' => 'plumbersstock',
        'kw' => 'kentucky'
    ];
    public $defaultVendor       = ['po', 'kw'];
    public $primaryVendors      = ['plumbersstock', 'orgill', 'stockmarket'];
    public $secondaryVendors    = ['kentucky', 'orgill_mo'];
    public $buffer              = 0;
    public $phraseExcluded      = ['butt', 'bastard', 'cock'];

    public function __construct(
        Listing $listing,
        Product $products,
        AmazonListing $amazonListings,
        AmazonQualifying $amazonQualifying,
        ProductAttribute $productAttribute,
        Catalog $catalog,
        ThrottlingListingsError $throttlingListingsError,
        ProcessingListingError $processingListingError,
        AmazonAsinProductType $amazonAsinProductType,
        CatalogItems $catalogItems,
        CatalogItemAsin $catalogItemAsin
    ) {
        parent::__construct();
        $this->catalog                  = $catalog;
        $this->listing                  = $listing;
        $this->products                 = $products;
        $this->amazonListings           = $amazonListings;
        $this->amazonQualifying         = $amazonQualifying;
        $this->productAttribute         = $productAttribute;
        $this->throttlingListingsError  = $throttlingListingsError;
        $this->processingListingError   = $processingListingError;
        $this->amazonAsinProductType    = $amazonAsinProductType;
        $this->catalogItems             = $catalogItems;
        $this->catalogItemAsin          = $catalogItemAsin;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $this->catalog->setSellerConfig(true);
        $type           = $this->option('type');
        $category       = $this->option('category');
        $appName        = $this->listing->app->getAppName();

        $this->info("Uploading [{$type}] type of listing using [{$category}] category...");

        if (!in_array($type, $this->types)) {
            $this->error("The option method is not accepting the value you provided, [{$type}] method is not valid.");
        }
        if (!in_array($category, $this->categories)) {
            $this->error("The option method is not accepting the value you provided, [{$category}] method is not valid.");
        }
        $items          = $this->amazonQualifying
                        ->selectRaw('
                            sku,
                            MIN(id) as id,
                            MAX(asin) as asin,
                            MAX(title) as title,
                            MAX(model_number) as model_number,
                            MAX(part_number) as part_number,
                            MAX(brand) as brand,
                            MAX(product_group) as product_group,
                            MAX(product_type) as product_type,
                            MAX(publisher) as publisher,
                            MAX(studio) as studio,
                            MAX(package_qty) as package_qty,
                            MAX(upc) as upc,
                            MAX(is_uploaded) as is_uploaded,
                            MAX(is_skipped) as is_skipped
                        ')
                        ->where('is_queued', '0')
                        ->groupBy('sku')
                        ->orderBy('id')
                        ->limit(50000)
                        ->get();
                        // dd($items);
        $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        foreach ($items as $item) {
            if ($item->getIsSkipped()) {
                // $this->info("Items with SKU [{$item->getSku()}] was rejected before...");
                continue;
            }
            if ($item->getIsUploaded()) {
                // $this->info("Items with SKU [{$item->getSku()}] was already uploaded...");
                continue;
            }

            $masterData = $this->aggregateData($item);

            $mySqlAttributes = $this->productAttribute->where('category', $masterData['product_type'])->first();
            if (!$mySqlAttributes){
                $this->error("Products attributes for this SKU [{$item->getSku()}] was not found...");
                continue;
            }
            $attributes = $mySqlAttributes->getAttributes()['attributes'];
            $masterData["attributes"] = array_filter(
                $masterData["attributes"],
                function ($key) use ($attributes) {
                    return in_array($key, $attributes);
                },
                ARRAY_FILTER_USE_KEY
            );

            $offerPrice = isset($masterData['attributes']["purchasable_offer"][0]['minimum_seller_allowed_price'][0]['schedule'][0]['value_with_tax'])
                        ? $masterData['attributes']["purchasable_offer"][0]['minimum_seller_allowed_price'][0]['schedule'][0]['value_with_tax']
                        : (float) 0.01;
            $offersData = [
                [
                    "marketplace_id"    => "ATVPDKIKX0DER",
                    "offer_type"        => "B2C",
                    "price" => [
                        "amount"                    => (float) $offerPrice,
                        "is_fulfilled_by_amazon"    => false
                    ]
                ]
            ];
            // dd($masterData);
            $response   = $this->listing->uploadListing($item->getSku(), $masterData, $offersData, $sellerId, $marketplaceId);

            if (isset($response['error'])) {
                $rawMsg         = $response['error']['message'];
                $jsonPart       = preg_replace('/^\[\d+\]\s*/', '', $rawMsg);
                $errData        = json_decode($jsonPart, true);
                $errData['sku'] = $item->getSku();
                $this->throttlingListingsError->create($errData);
            } else {
                $sellerSku      = $response->sku;
                $uploadStatus   = $response->status;
                if ($uploadStatus == 'ACCEPTED') {
                    if (!$item->update(['is_queued' => 1])) {
                        $this->error("Error upload on SKU [{$sellerSku}]...");
                    }
                    $this->info("SKU [{$sellerSku}] was successfully uploaded with the status of [{$uploadStatus}]");
                } else {
                    foreach ($response->issues as $issue) {
                        $errArrayData           = json_decode(json_encode($issue), true);
                        $errArrayData['sku']    = $item->getSku();
                        $this->processingListingError->create($errArrayData);
                        $this->error("Error on SKU [{$item->getSku()}] with a message: [{$issue->message}]");
                        if ($issue->message == "The Amazon product type specified is invalid or not supported.") {
                            $item->update(['is_skipped' => 1]);
                        }
                    }
                    // sleep(0.5);
                    // dd();
                }
            }
        }
    }

    public function aggregateData($item)
    {
        $seller         = $item->getSeller();
        $sku            = $item->getSku();
        $asin           = $item->getAsin();
        $title          = $item->getTitle();
        $brand          = $item->getBrand();
        $modelNumber    = $item->getModelNumber();
        $partNumber     = $item->getPartNumber();
        $productGroup   = $item->getProductGroup();
        $productType    = $item->getProductType();
        $publisher      = $item->getPublisher();
        $studio         = $item->getStudio();
        $packageQty     = 1;
        $upc            = $item->getUpc();
        $parentSku      = str_replace(['po', 'kw'], '', $sku);
        $listPrice      = (float) 0.01;
        $currency       = "USD";
        $sellertId      = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $ourPrice       = (float) 0.01;
        $minPrice       = (float) 0.01;
        $maxPrice       = (float) 0.01;
        $language       = "en_US";
        $product        = $this->products->where('productId', (int) $parentSku)->first(); // This from Typhoeus Product MongoDB
        $productTypeMdl = $this->amazonAsinProductType->where('asin', $asin)->first(); // This from Mysql Shipping DB
        $catalogItems   = $this->catalogItems->where('upc', $upc)->first(); // This from MongoDB Amazon_sp collection
        $catalogItemAsin = $this->catalogItemAsin->where('upc', $upc)->first(); // This from MongoDB Amazon_sp collection

        if ($product->count() > 0) {
            if ($product->getAmazon() == []) {
                $attributes = [];
            } else {
                $attributes = $product->getAmazon();
            }
            $asin       = $asin ?? $attributes['asin'];
            $dimensions = $product->getDimension();
            $listPrice  = isset($attributes['content']['attributes']['ListPrice']['Amount']) ? (float) $attributes['content']['attributes']['ListPrice']['Amount']
                        : isset($product['pricing']['listPrice']) ? (float) $product['pricing']['listPrice']
                        : (float) $listPrice;
            $currency   = $currency ?? $attributes['content']['attributes']['ListPrice']['CurrencyCode'];
            $sellerData = $attributes['PO_Amazon'] ?? [];
            $ourPrice   = isset($product['pricing']['price'])
                    ? (float) $product['pricing']['price']
                    : $ourPrice;
            $minPrice   = isset($product['pricing']['price'])
                    ? (float) $product['pricing']['price']
                    : $minPrice;
            $maxPrice   = isset($product['pricing']['listPrice'])
                    ? (float) $product['pricing']['listPrice']
                    : $listPrice;

            if ($sellerData != []) {
                $ourPrice = isset($sellerData['pricing']['ours'])
                    ? (float) $sellerData['pricing']['ours']
                    : $ourPrice;

                $minPrice = isset($sellerData['price_range']['min'])
                    ? (float) $sellerData['price_range']['min']
                    : $minPrice;

                $maxPrice = isset($sellerData['price_range']['max'])
                    ? (float) $sellerData['price_range']['max']
                    : $listPrice;
            }

            $title          = $attributes['content']['attributes']['Title'] ?? $title;
            $brand          = $attributes['content']['attributes']['Brand'] ?? $brand;
            $manufacturer   = $attributes['content']['attributes']["Manufacturer"] ?? $brand;
            $modelNumber    = $attributes['content']['attributes']["Model"] ?? $modelNumber;
            $partNumber     = $attributes['content']['attributes']["PartNumber"] ?? $partNumber;
            $itemLength     = isset($dimensions["length"]) && $dimensions["length"] > 0 ? $dimensions["length"] : 1;
            $itemWidth      = isset($dimensions["width"]) && $dimensions["width"] > 0 ? $dimensions["width"] : 1;
            $itemHeight     = isset($dimensions["height"]) && $dimensions["height"] > 0 ? $dimensions["height"] : 1;
            $itemWeight     = isset($dimensions["weight"]) && $dimensions["weight"] > 0 ? $dimensions["weight"] : 1;
            $keywords       = $this->sanitizeString($product->getKeywords());
            $packageQty     = isset($attributes['content']['attributes']['PackageQuantity'])
                                ? (int) $attributes['content']['attributes']['PackageQuantity']
                                : ($packageQty ?? 1);
            $color          = "Black";
            $numberOfBox    = 1;
            $packageWeight  = isset($attributes['content']['attributes']['PackageDimensions']['Weight'])
                ? (float) $attributes['content']['attributes']['PackageDimensions']['Weight']
                : $itemWeight;

            $packageHeight  = isset($attributes['content']['attributes']['PackageDimensions']['Height'])
                ? (float) $attributes['content']['attributes']['PackageDimensions']['Height']
                : $itemHeight;

            $packageLength  = isset($attributes['content']['attributes']['PackageDimensions']['Length'])
                ? (float) $attributes['content']['attributes']['PackageDimensions']['Length']
                : $itemLength;

            $packageWidth   = isset($attributes['content']['attributes']['PackageDimensions']['Width'])
                ? (float) $attributes['content']['attributes']['PackageDimensions']['Width']
                : $itemWidth;
            $unitInWeigth   = "pounds";
            $unitInDims     = "inches";
            $bulletPoints   = [
                [ "value" => "Style: Default" ]
            ];

            if (isset($product['features'])) {
                foreach (array_slice($product['features'], 0, 9) as $bullet) {
                    if ($this->containsUrl($bullet)) {
                        continue;
                    }
                    $cleanString = preg_replace('/[^\x20-\x7E]/', '', $bullet);
                    $bulletPoints[] = ["value" => $cleanString];
                }
            }
            // $bulletPoints = [
            //     [
            //         "value" => 'Spacious & Organized Storage: 9 pockets, including 5 removable clear pouches (1 large, 4 small), perfect for organizing tools, office supplies, school essentials, and craft materials. Outer dimensions: 23” x 20” x 10”; inner dimensions: 22” x 18” x 9”'
            //     ],
            //     [
            //         "value" => 'Ideal for Crafters: Fits Cricut Maker, Cricut Explore Air, Silhouette Cameo 3, or Sizzix Big Shot, plus laptop, materials, cartridges, and tools.'
            //     ],
            //     [
            //         "value" => 'Smart Organization: Side pockets hold cartridges/embellishments, front/back pockets fit 12” x 12” paper, and a large cubby stores your cutting machine.'
            //     ],
            //     [
            //         "value" => 'Durable & Travel-Friendly: Heavy-duty polyester fabric for tear resistance.'
            //     ],
            //     [
            //         "value" => '360 degree Swivel Wheels: Smooth-rolling, pivoting wheels for easy transport.'
            //     ]
            // ];
            // dd(strip_tags(html_entity_decode($product->getDescription())));
            $description    = strip_tags($product->getDescription());
            $material       = "not_applicable";
            $finishType     = "not_applicable";
            $containerType  = ['Aerosol Can', 'Bottle', 'Can', 'Cartridge', 'Drum', 'Pail', 'Syringe', 'Tube'];

            if (!$catalogItems) {
                $suggestedCatalogAttr   = $this->catalog->getCatalogItemList($upc);
                if (!is_null($suggestedCatalogAttr->getPayload()) && $suggestedCatalogAttr->getPayload()->items != []) {
                    $itemCat    = $suggestedCatalogAttr->getPayload()->getItems()[0];
                    $attriSets  = $itemCat["attribute_sets"][0];
                    $color      = $attriSets['color'] ?? 'white';
                    $material   = isset($attriSets["material_type"])
                        ? $attriSets["material_type"][0]
                        : 'Not Applicable';
                    $finishType = $attriSets['finish_type'] ?? 'white';
                } else {
                    $itemCat    = $attributes['content'] ?? [];
                    $attriSets  = $itemCat["attributes"] ?? [];
                    $color      = $attriSets['Color'] ?? 'white';
                    $material   = 'Not Applicable';
                    $finishType = 'white';
                }
            } else {
                $attriSets  = $catalogItems->AttributeSets[0] ?? [];
                $color      = $attriSets['Color'] ?? 'white';
                $material   = isset($attriSets["MaterialType"])
                    ? $attriSets["MaterialType"][0]
                    : 'Not Applicable';
                $finishType = $attriSets['FinishType'] ?? 'white';
            }
            // $suggestedCatalogAttrNew    = $this->catalog->searchAsinByIndentifier($upc);
            $suggestedCatalogAttrNew    = $catalogItemAsin ?? $this->catalog->getCatalogItemByASIN($asin);
            $itemCatalogNew             = $suggestedCatalogAttrNew ?? [];
            $identifier = [
                [
                    "value"             => $this->formatUpc($upc),
                    "type"              => "upc",
                    "marketplace_id"    => $marketplaceId
                ]
            ];

            if ($itemCatalogNew != []) {
                $itemNew    = $itemCatalogNew;
                $finishType =  $itemNew['attributes']['finish_type'][0]->value ?? 'white';
                $typeCode   = $this->identifyBarcodeType($upc);

                if ($typeCode == 'EAN') {
                    $identifier = [
                        [
                            "value"             => $this->formatEan($upc),
                            "type"              => "ean",
                            "marketplace_id"    => "ATVPDKIKX0DER"
                        ]
                    ];
                } else {
                    $identifier = [
                        [
                            "value"             => $this->formatUpc($upc),
                            "type"              => "upc",
                            "marketplace_id"    => $marketplaceId
                        ]
                    ];
                }
                if (!is_null($itemNew->asin) || $asin != $itemNew->asin) {
                    $asin           = $itemNew->asin ?? $asin;
                    $productType    = $productTypeMdl
                        ? $productTypeMdl->getProductType()
                        : $this->catalog->getProductTypeByAsin($asin);
                }
            } else {
                $typeCode   = $this->identifyBarcodeType($upc);
                if ($typeCode == 'EAN') {
                    $identifier = [
                        [
                            "value"             => $this->formatEan($upc),
                            "type"              => "ean",
                            "marketplace_id"    => "ATVPDKIKX0DER"
                        ]
                    ];
                } else {
                    $identifier = [
                        [
                            "value"             => $this->formatUpc($upc),
                            "type"              => "upc",
                            "marketplace_id"    => $marketplaceId
                        ]
                    ];
                }
                if (isset($attributes['PO_Amazon']) && isset($attributes['PO_Amazon']['skus'][0])) {
                    $poAmazon = isset($attributes['PO_Amazon']['skus'])
                        ? $attributes['PO_Amazon']['skus'][0]
                        : [];
                    $asin     = $poAmazon['asin'] ?? $asin;
                }
                if (isset($attributes['content'])) {
                    $att            = $attributes['content']["attributes"] ?? [];
                    $productType    = $att["ProductTypeName"] ?? "PLUMBING_FIXTURE";
                }
            }

            if ($packageWeight == 0) {
                $packageWeight = 0.1;
            }

            if ($productType != $item->getProductType()) {
                $productType = $productTypeMdl
                    ? $productTypeMdl->getProductType()
                    : $this->catalog->getProductTypeByAsin($asin);
            }

            if (is_null($productType)) {
                $productType = $item->getProductType();
            }

            $keywords   = $this->removeExcludedWords($keywords);
            $color      = $this->truncate($color);
            $title      = $this->cleanItemName($title);
        }
        // dd($productType);
        $data = [
            'product_type'  => empty($productType) ? null : $productType,
            'requirements'  => 'LISTING',
            'attributes'    => [
                "condition_type"        => [[ "value" => "new_new", "marketplace_id" => $marketplaceId ]],
                "closure"   => [
                    [
                        'marketplace_id'    => $marketplaceId,
                        'type'              => [
                            [
                                "language_tag"  => $language,
                                "value"         =>  "Not Applicable"
                            ]
                        ]
                    ]
                ],
                "door"      => [
                    [
                        "marketplace_id"    => $marketplaceId, // Required marketplace ID
                        "material_type"  => [
                            [
                                "language_tag" => $language, // Required language tag
                                "value"        => "Stainless Steel"
                            ]
                        ],
                        "orientation"       => [
                            [
                                "language_tag"      => $language, // Required language tag
                                "value"             => "Left"
                            ]
                        ],
                        "style"             => [
                            [
                                "marketplace_id"    => $marketplaceId,
                                "language_tag"      => $language,
                                "value"             => "Mirrored"
                            ]
                        ]
                    ]
                ],
                "target_species" => [
                    [
                        "value" => "not_applicable",
                        "language_tag" => $language,
                        "marketplace_id" => $marketplaceId
                    ]
                ],
                "list_price"            => [[ "value" => $listPrice, "currency" => $currency, "marketplace_id" => $marketplaceId ]],
                "item_name"             => [[ "value" => $title, "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "product_description"   => [[ "value" => $description, "language_tag" => $language, "marketplace_id" => $marketplaceId]],
                "customer_id"           => [[ "value" => "459219857", "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "brand"                 => [[ "value" => $brand, "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "manufacturer"          => [[ "value" => $manufacturer, "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "model_name"            => [[ "value" => $modelNumber ]],
                "model_number"          => [[ "value" => $modelNumber ]],
                "part_number"           => [[ "value" => $partNumber ]],
                "item_type_keyword"     => [[ "value" => $keywords, 'marketplace_id' => $marketplaceId ]],
                "lifestyle"        => [[ "value" => "not_applicable", "marketplace_id" => $marketplaceId ]],
                "supplier_declared_dg_hz_regulation" => [[ "value" => "not_applicable", "marketplace_id" => $marketplaceId ]],
                "cpsia_cautionary_statement" => [[ "value" => "no_warning_applicable", "marketplace_id" => $marketplaceId ]],
                "external_testing_certification" => [[ "value" => "not_applicable", "marketplace_id" => $marketplaceId ]],
                "hair_type" => [[ "value" => "not_applicable", "marketplace_id" => $marketplaceId ]],
                "required_product_compliance_certificate" => [
                    [
                        "value"             => "Not Applicable",
                        "marketplace_id"    => $marketplaceId
                    ]
                ],
                "item_width_diameter_height" => [
                    [
                        "diameter" => [
                            "value" => 27,  // Replace with actual product diameter
                            "unit" => "inches"
                        ],
                        "height" => [
                            "value" => 2.25,  // Replace with actual product height
                            "unit" => "inches"
                        ],
                        "width" => [
                            "value" => 46,  // Replace with actual product width
                            "unit" => "inches"
                        ],
                        "marketplace_id" =>  $marketplaceId
                    ]
                ],
                "target_audience_keyword" => [
                    [
                        "value" => "Unisex Adult",
                        "language_tag" => $language,
                        "marketplace_id" => $marketplaceId
                    ],
                    [
                        "value" => "Women",
                        "language_tag" => $language,
                        "marketplace_id" => $marketplaceId
                    ]
                ],
                "target_audience" => [
                    [
                        "value" => "Unisex Adult",
                        "language_tag" => $language,
                        "marketplace_id" => $marketplaceId
                    ]
                ],
                "safety_warning" => [
                    [
                        "value" => "Warning: Choking hazard - small parts. Not for children under 3 years.",
                        "language_tag" => $language,
                        "marketplace_id" => $marketplaceId
                    ],
                    [
                        "value" => "For external use only. Avoid contact with eyes.",
                        "language_tag" => $language,
                        "marketplace_id" => $marketplaceId
                    ]
                ],
                "unit_count"            => [
                    [
                        "value" => 1,
                        "type"  => [
                            "language_tag"  => $language,
                            "value"         => "Ounce"
                        ],
                        'marketplace_id' => $marketplaceId
                    ]
                ],
                "weight_capacity" => [
                    [
                        "maximum" => [
                            [
                                "value" => 50.0, // Weight capacity as a number
                                "unit" => "pounds" // Must be one of the allowed unit values
                            ]
                        ],
                        "marketplace_id" => "ATVPDKIKX0DER" // US marketplace
                    ]
                ],
                "number_of_wheels"       => [[ "value" => 2, 'marketplace_id' => $marketplaceId ]],
                "hazmat"                => [[ "value" => '0', 'aspect' => 'transportation_regulatory_class', 'marketplace_id' => $marketplaceId ]],
                "number_of_items"       => [[ "value" => $packageQty, 'marketplace_id' => $marketplaceId ]],
                "color"                 => [[ "value" => $color ]],
                "ink_color"                 => [[ "value" => $color ]],
                "style"                 => [[ "value" => "Modern", "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "externally_assigned_product_identifier" => $identifier,
                "is_fragile"            => [[ "value" => false, 'marketplace_id' => $marketplaceId ]],
                "item_shape"            => [[ "value" => 'circle', 'marketplace_id' => $marketplaceId ]],
                "room_type"             => [[ "value" => 'comfort_room', 'marketplace_id' => $marketplaceId ]],
                "is_assembly_required"  => [[ "value" => false, 'marketplace_id' => $marketplaceId ]],
                "number_of_drawers"     => [[ "value" => 0, 'marketplace_id' => $marketplaceId ]],
                "batteries_required"    => [[ "value" => false ]],
                "batteries_included"    => [[ "value" => false ]],
                "included_components"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "breed_recommendation"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "material"              => [[ "value" => $material, 'marketplace_id' => $marketplaceId ]],
                "number_of_boxes"       => [[ "value" => (string) $numberOfBox ]],
                "item_weight"           => [[ "value" => $itemWeight, "unit" => $unitInWeigth ]],
                "website_shipping_weight" => [[ "value" => '1d-8', "unit" => $unitInWeigth ]],
                "item_package_weight"   => [[ "value" => $packageWeight, "unit" => $unitInWeigth ]],
                "item_package_dimensions" => [[
                    "width"     => [ "value" => (float) $packageWidth, "unit" => $unitInDims ],
                    "height"    => [ "value" => (float) $packageHeight, "unit" => $unitInDims ],
                    "length"    => [ "value" => (float) $packageLength, "unit" => $unitInDims ],
                    "weight"    => [ "value" => (float) $unitInWeigth, "unit" => $unitInWeigth ]
                ]],
                "designer"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "publisher"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "genre"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "publication_date"   => [[ "value" => "1970-01-01", 'marketplace_id' => $marketplaceId ]],
                "author"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "composer"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "actor"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "artist"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "performance"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "director"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "binding"   => [[ "value" => "dvd_audio", 'marketplace_id' => $marketplaceId ]],
                "opening_mechanism"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "audio_input"   => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "number_of_channels"   => [[ "value" => "1", 'marketplace_id' => $marketplaceId ]],
                "lens" => [
                    [
                        "coating_description" => [
                            [
                                "value" => "UV Protection Coating", // Must be one of the allowed enum values
                                "language_tag" => "en_US"
                            ]
                        ],
                        "color" => [
                            [
                                "value" => "Blue", // Lens color
                                "language_tag" => "en_US"
                            ]
                        ],
                        "material" => [
                            [
                                "value" => "Polycarbonate", // Must be one of the allowed enum values
                                "language_tag" => "en_US"
                            ]
                        ],
                        "width" => [
                            [
                                "value" => "29", // Lens width
                                "unit" => "millimeters", // Must be one of the allowed unit values
                                "language_tag" => "en_US"
                            ]
                        ],
                        "marketplace_id" => "ATVPDKIKX0DER" // US marketplace
                    ]
                ],
                "ink" => [
                    [
                        "base" => [
                            [
                                "value" => "water" // Must be one of the allowed enum values
                            ]
                        ],
                        "color" => [
                            [
                                "value" => "Multicolor", // Must be one of the allowed enum values
                                "language_tag" => $language // Language code
                            ]
                        ],
                        "marketplace_id" => "ATVPDKIKX0DER" // US marketplace
                    ]
                ],
                "point" => [
                    [
                        "marketplace_id" => $marketplaceId, // US marketplace
                        "type" => [
                            [
                                "value" => "Broad", // Must be one of the allowed enum values
                                "language_tag" => $language // Language code
                            ]
                        ]
                    ]
                            ],
                "rod" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "length" => [
                            [ "value" => (float) $itemLength, "unit" => $unitInDims ]
                        ]
                    ]
                ],
                "item_length" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "value" => $itemLength, // Numeric value
                        "unit" => $unitInDims // Must be "inches"
                    ]
                ],
                "item_width_height" => [[
                    "width"     => [ "value" => (float) $itemWidth, "unit" => $unitInDims ],
                    "height"    => [ "value" => (float) $itemHeight, "unit" => $unitInDims ]
                ]],
                "item_dimensions" => [[
                    "width"     => [ "value" => (float) $itemWidth, "unit" => $unitInDims ],
                    "height"    => [ "value" => (float) $itemHeight, "unit" => $unitInDims ],
                    "length"    => [ "value" => (float) $itemLength, "unit" => $unitInDims ],
                    "weight"    => [ "value" => (float) $itemWeight, "unit" => $unitInWeigth ]
                ]],
                "item_depth_width_height" => [[
                    "width"     => [ "value" => (float) $itemWidth, "unit" => $unitInDims ],
                    "height"    => [ "value" => (float) $itemHeight, "unit" => $unitInDims ],
                    "depth"     => [ "value" => (float) 3, "unit" => $unitInDims ]
                ]],
                "item_length_width" => [[
                    "length"    => [ "value" => (float) $itemLength, "unit" => $unitInDims ],
                    "width"     => [ "value" => (float) $itemWidth, "unit" => $unitInDims ]
                ]],
                "item_length_width_height" => [[
                    "length"    => [ "value" => (float) $itemLength, "unit" => $unitInDims ],
                    "width"     => [ "value" => (float) $itemWidth, "unit" => $unitInDims ],
                    "height"    => [ "value" => (float) $itemHeight, "unit" => $unitInDims ],
                ]],
                "item_width_height_thickness" => [[
                    "height"    => [ "value" => (float) $itemHeight, "unit" => $unitInDims ],
                    "width"     => [ "value" => (float) $itemWidth, "unit" => $unitInDims ],
                    "thickness" => [ "value" => (float) 1.0, "unit" => $unitInDims ],
                ]],
                "item_length_width_thickness" => [[
                    "length"    => [ "value" => (float) $itemLength, "unit" => $unitInDims ],
                    "width"     => [ "value" => (float) $itemWidth, "unit" => $unitInDims ],
                    "thickness" => [ "value" => (float) 1.0, "unit" => $unitInDims ],
                ]],
                "item_diameter_length" => [[
                    "length"    => [ "value" => (float) number_format($itemLength, 3, '.', ''), "unit" => $unitInDims ],
                    "diameter"  => [ "value" => (float) number_format(10.0, 3, '.', ''), "unit" => $unitInDims ]
                ]],
                "bullet_point"          => $bulletPoints,
                "country_of_origin"     => [[ "value" => "US" ]],
                "power_source_type"     => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "fabric_type"           => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "special_feature"       => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "mounting_type"         => [[ "value" => "Wall mount", 'marketplace_id' => $marketplaceId ]],
                "installation_type"     => [[ "value" => "Wall Mount", 'marketplace_id' => $marketplaceId ]],
                "drain_type"            => [[ "value" => "Not Applicable", 'marketplace_id' => $marketplaceId ]],
                "finish_type"           => [[ "value" => $finishType, 'marketplace_id' => $marketplaceId ]],
                "construction_type"     => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "generic_keyword"       => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "operation_mode"        => [[ "value" => "Automatic", 'marketplace_id' => $marketplaceId ]],
                "is_dishwasher_safe"    => [[ "value" => "yes", 'marketplace_id' => $marketplaceId ]],
                "contains_liquid_contents" => [[ "value" => "no", 'marketplace_id' => $marketplaceId ]],
                "number_of_pieces"      => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "input_voltage"         => [[ "value" => 120, "unit" => 'volts', 'marketplace_id' => $marketplaceId ]],
                "output_voltage"         => [[ "value" => 120, "unit" => 'volts', 'marketplace_id' => $marketplaceId ]],
                "output_current"         => [[ "value" => 25, "unit" => 'amps', 'marketplace_id' => $marketplaceId ]],
                "number_of_ports"         => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "maximum_range"         => [[ "value" => 20, "unit" => 'meters', 'marketplace_id' => $marketplaceId ]],
                "warranty_description"  => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "compatible_material"   => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "specific_uses_for_product" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "light_color"           => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "light_type"            => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "voltage"               => [[ "value" => 110, "unit" => 'volts', 'marketplace_id' => $marketplaceId ]],
                "sheet_count"           => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "number_of_shelves"     => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "number_of_handles"     => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "drive_system"          => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "age_range_description" => [[ "value" => "Adult", "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "shelf_type"            => [[ "value" => "not_applicable", "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "setting_type"          => [[ "value" => "not_applicable", "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "surface_recommendation" => [[ "value" => "not_applicable", "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
                "controller_type"       => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "supported_devices_quantity" => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "connectivity_technology" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "compatible_devices"    => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "exterior_finish"       => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "connector_type"        => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "compatible_phone_models" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "configuration"         => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "switch_type"           => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "collection"            => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "care_instructions"     => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "size"                  => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "metal_type"            => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "capacity"              => [[ "value" => 1, "unit" => "load", 'marketplace_id' => $marketplaceId ]],
                "occasion_type"         => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "automotive_fit_type"   => [[ "value" => "universal_fit", 'marketplace_id' => $marketplaceId ]],
                "compatible_with_vehicle_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "is_ul_listed"          => [[ "value" => false, 'marketplace_id' => $marketplaceId ]],
                "electric_fan_design"   => [[ "value" => "blower", 'marketplace_id' => $marketplaceId ]],
                "recommended_uses_for_product" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "washer_type"           => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "item_thickness"        => [[ "string_value"   => "2", "unit" => "inches", 'marketplace_id' => $marketplaceId ]],
                "outside_diameter"      => [[ "string_value"   => "40", "unit" => "millimeters", 'marketplace_id' => $marketplaceId ]],
                "import_designation"    => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "department"            => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "headphones_form_factor" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "form_factor" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "defrost_system" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "heel_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "filter_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "caster_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "frame_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "is_product_cordless" => [[ "value" => false, 'marketplace_id' => $marketplaceId ]],
                "measurement_accuracy"  => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "fit_type"              => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "lock_type"             => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "is_oem_authorized"     => [[ "value" => false, 'marketplace_id' => $marketplaceId ]],
                "is_heat_sensitive"     => [[ "value" => false, 'marketplace_id' => $marketplaceId ]],
                "headphones_ear_placement" => [[ "value" => "in_ear", 'marketplace_id' => $marketplaceId ]],
                "measurement_system"    => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "specification_met"     => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "gauge"                 => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "stud_size"             => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "noise_level"             => [[ "value" => 14.4, "unit"  => "decibels", 'marketplace_id' => $marketplaceId ]],
                "air_flow_displacement"             => [[ "value" => 1200, "unit"  => "cubic_feet_per_minute", 'marketplace_id' => $marketplaceId ]],
                "annual_energy_consumption"             => [[ "value" => 10000, "unit"  => "watts", 'marketplace_id' => $marketplaceId ]],
                "wattage"             => [[ "value" => 200, "unit"  => "watts", 'marketplace_id' => $marketplaceId ]],
                "output_wattage"             => [[ "value" => 200, "unit"  => "watts", 'marketplace_id' => $marketplaceId ]],
                "number_of_vents"             => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "number_of_labels"      => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "operating_voltage"     => [[ "value" => 110, "unit" => 'volts', 'marketplace_id' => $marketplaceId ]],
                "maximum_tilt_angle"     => [[ "value" => 45, "unit" => 'degrees', 'marketplace_id' => $marketplaceId ]],
                "minimum_compatible_size"     => [[ "value" => 23.5, "unit" => 'inches', 'marketplace_id' => $marketplaceId ]],
                "maximum_compatible_size"     => [[ "value" => 42.5, "unit" => 'inches', 'marketplace_id' => $marketplaceId ]],
                "movement_type"             => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "hand_orientation"             => [[ "value" => "Right", 'marketplace_id' => $marketplaceId ]],
                "golf_club_flex"             => [[ "value" => "Regular", 'marketplace_id' => $marketplaceId ]],
                "golf_club_loft"             => [[ "value" => 8.5, "unit" => "degrees", 'marketplace_id' => $marketplaceId ]],
                "item_diameter"             => [[ "decimal_value" => 1.25, "unit" => "inches", 'marketplace_id' => $marketplaceId ]],
                "pattern"             => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "indoor_outdoor_usage"             => [[ "value" => "indoor", 'marketplace_id' => $marketplaceId ]],
                "light_fixture_form"             => [[ "value" => "sconce", 'marketplace_id' => $marketplaceId ]],
                "fishing_technique"             => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "target_gender"             => [[ "value" => "unisex", 'marketplace_id' => $marketplaceId ]],
                "special_size_type"             => [[ "value" => "Plus Size", 'marketplace_id' => $marketplaceId ]],
                "load_capacity"             => [[ "value" => "6", "unit" => 'pounds', 'marketplace_id' => $marketplaceId ]],
                "weather_resistance_description" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "Output Type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "video_capture_resolution" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "control_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "video_capture_format" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "connectivity_protocol" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "output_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "reusability" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "heat_output" => [[ "value" => "100", "unit" => "degrees_celsius", 'marketplace_id' => $marketplaceId ]],
                "base_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "total_power_outlets" => [[ "value" => 2, 'marketplace_id' => $marketplaceId ]],
                "number_of_positions" => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "theme" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "subject_character" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "fuel_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "terminal_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "international_protection_rating" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "heating_element_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "display_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "circuit_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "alarm" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "material_feature" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "item_form" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "scent" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "ingredients" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "access_location" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "cycle_options" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "lamp_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "circuit_breaker_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "strap_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "cooling_power" => [[ "value" => 50, "unit" => "kilowatts", 'marketplace_id' => $marketplaceId ]],
                "energy_star" => [[ "value" => "5 Star", 'marketplace_id' => $marketplaceId ]],
                "maximum_weight_recommendation" => [[ "value" => 50, "unit" => "kilograms", 'marketplace_id' => $marketplaceId ]],
                "upper_temperature_rating" => [[ "value" => 32.02, "unit" => "degrees_celsius", 'marketplace_id' => $marketplaceId ]],
                "maximum_current" => [[ "value" => 50, "unit" => "amps", 'marketplace_id' => $marketplaceId ]],
                "cutting_width" => [[ "value" => 32.02, "unit" => "inches", 'marketplace_id' => $marketplaceId ]],
                "item_volume" => [[ "value" => 5, "unit" => "liters", 'marketplace_id' => $marketplaceId ]],
                "pitch_circle_diameter" => [[ "value" => 112.0, "unit" => "inches", 'marketplace_id' => $marketplaceId ]],
                "item_offset" => [[ "value" => -35, "unit" => "millimeters", 'marketplace_id' => $marketplaceId ]],
                "load_index" => [[ "value" => 91, 'marketplace_id' => $marketplaceId ]],
                "hole_count" => [[ "value" => 18, 'marketplace_id' => $marketplaceId ]],
                "speed_rating" => [[ "value" => "L", 'marketplace_id' => $marketplaceId ]],
                "maximum_operating_pressure" => [[ "value" => 290, "unit" => "bars", 'marketplace_id' => $marketplaceId ]],
                "maximum_compatible_number_of_seats" => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "roll_quantity" => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "number_of_poles" => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "number_of_sets" => [[ "value" => 1, 'marketplace_id' => $marketplaceId ]],
                "ultraviolet_light_protection" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "heating_method" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "cooling_method" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "maximum_rotational_speed" => [[ "value" => "2000", "unit" => "rpm", 'marketplace_id' => $marketplaceId ]],
                "speaker_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "speaker_amplification_type" => [[ "value" => "not_applicable", 'marketplace_id' => $marketplaceId ]],
                "speakers_maximum_output_power" => [[ "value" => "180", "unit" => "watts", 'marketplace_id' => $marketplaceId ]],
                "water_resistance_level" => [[ "value" => "water_resistant", 'marketplace_id' => $marketplaceId ]],
                "brake_style" => [[ "value" => "Front Braking", 'marketplace_id' => $marketplaceId ]],
                "display" => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Digital"
                            ]
                        ]
                    ]
                ],
                "rim" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "size" => [
                            [
                                "value" => 6.5,
                                "unit" => "inches"
                            ]
                        ],
                        "width" => [
                            [
                                "value" => 17.5,
                                "unit" => "inches"
                            ]
                        ]
                    ]
                ],
                "wheel" => [
                    [
                        "marketplace_id" => "ATVPDKIKX0DER",
                        "backspacing" => [
                            [
                                "value" => 5,
                                "unit" => "inches"
                            ]
                        ],
                        "size" => [
                            [
                                "value" => 25.0,
                                "unit" => "millimeters"
                            ]
                        ],
                        "material" => [
                            [
                                "value" => "Polyurethane", // Must be one of the allowed enum values
                                "language_tag" => "en_US"
                            ]
                        ],
                        "size" => [
                            [
                                "value" => 26, // Wheel size as a number
                                "unit" => "inches" // Must be one of the allowed unit values
                            ]
                        ],
                        "type" => [
                            [
                                "value" => "Caster", // Must be one of the allowed enum values or a custom string
                                "language_tag" => "en_US"
                            ]
                        ]
                    ]
                ],
                "bore" => [
                    [
                        "marketplace_id" => "ATVPDKIKX0DER",
                        "diameter" => [
                            [
                                "string_value" => "64.1",
                                "unit" => "millimeters"
                            ]
                        ]
                    ]
                ],
                "grit" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value" => "Silicon Carbide" // ✅ Ensure this is a valid grit material
                            ]
                        ],
                        "number" => [
                            [
                                "value" => 36 // ✅ Ensure this is a valid grit number
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Fine" // ✅ Must be one of the allowed values
                            ]
                        ]
                    ]
                ],
                "light_source" => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "LED"
                            ]
                        ]
                    ]
                ],
                "tool_flute" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "length" => [
                            [
                                "string_value" => "100",
                                "unit" => "millimeters"
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Straight"
                            ]
                        ]
                    ]
                ],
                "heater_surface" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value" => "Tempered Glass"
                            ]
                        ]
                    ]
                ],
                "number_of_heating_elements" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "value" => 1
                    ]
                ],
                "shade" => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "color" => [
                            [
                                "language_tag" => $language,
                                "value" => "Black" // Must be a valid color from the list
                            ]
                        ],
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value" => "Metal" // Must be a valid material from the list
                            ]
                        ]
                    ]
                ],
                "top" => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "color" => [
                            [
                                "language_tag" => $language,
                                "value" => "Blue" // Must be a valid color from the list
                            ]
                        ],
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value" => "Ash Wood" // Must be a valid material from the list
                            ]
                        ]
                    ]
                ],
                "frame" => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "material" => [
                            [
                                "language_tag" => "en_US",
                                "value" => "Alloy Steel" // Must be a valid type from the list
                            ]
                        ],
                        "joint_type" => [
                            [
                                "language_tag" => "en_US",
                                "value" => "Dowel Joint" // Must be a valid type from the list
                            ]
                        ]
                    ]
                ],
                "sleeve"            => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "cuff_style" => [
                            [
                                "language_tag" => $language,
                                "value" => "French Cuff"
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Long Sleeve"
                            ]
                        ]
                    ]
                ],
                "neck"            => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "neck_style" => [
                            [
                                "language_tag" => $language,
                                "value" => "Collared Neck"
                            ],
                            [
                                "language_tag" => $language,
                                "value" => "V Neck"
                            ]
                        ]
                    ]
                ],
                "bowl"            => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "material_type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Stainless Steel"
                            ]
                        ]
                    ]
                ],
                "shaft"            => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "length" => [
                            [
                                "decimal_value" => 10.75,
                                "string_value" => "10.75 cm",
                                "unit" => "centimeters"
                            ]
                        ],
                        "material" => [
                            [
                                "language_tag" => "en_US",
                                "value" => "Graphite"
                            ]
                        ]
                    ]
                ],
                "insulation"            => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value"        => "Polyethylene"
                            ]
                        ]
                    ]
                ],
                "contact"              => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value"        => "Normally Ope"
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value"        => "Stainless Steel"
                            ]
                        ]
                    ]
                ],
                "head"                  => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "diameter" => [
                            [
                                "string_value" => "4", // Example inside diameter value
                                "unit"         => "inches" // Must be one of: "angstrom", "centimeters", "decimeters", "feet", "hundredths_inches", "inches", "kilometers", "meters", "micrometer", "miles", "millimeters", "mils", "nanometer", "picometer", "yards"
                            ]
                        ],
                        "size" => [
                            [
                                "language_tag" => $language,
                                "value"        => "1.5 inches"
                            ]
                        ],
                        "style" => [
                            [
                                "language_tag" => $language,
                                "value"        => "Bugle"
                            ]
                        ],
                        "type" => [
                            [
                                "value" => "Button"
                            ]
                        ],
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value" => "Stainless Steel"
                            ]
                        ]
                    ]
                ],
                "inside"                => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "diameter" => [
                            [
                                "string_value" => "2.5", // Example inside diameter value
                                "unit"         => "inches" // Must be one of: "angstrom", "centimeters", "decimeters", "feet", "hundredths_inches", "inches", "kilometers", "meters", "micrometer", "miles", "millimeters", "mils", "nanometer", "picometer", "yards"
                            ]
                        ]
                    ]
                ],
                "connector_gender"      => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "length" => [
                            [
                                "marketplace_id" => $marketplaceId,
                                "language_tag"   => $language, // e.g., "en_US"
                                "value"          => "Female-to-Female"
                            ]
                        ],
                        "value" => 1
                    ]
                ],
                "cable"                 => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "length" => [
                            [
                                "decimal_value" => 6.0, // Example cable length value
                                "unit"          => "meters" // Must be one of: "centimeters", "feet", "inches", "meters"
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Coaxial" // Use a valid enum value
                            ]
                        ]
                    ]
                ],
                "lower_temperature_rating"         => [
                    [
                        "unit"  => "degrees_celsius",
                        "value" => 30.0,
                        'marketplace_id' => $marketplaceId
                    ]
                ],
                "input_current"         => [
                    [
                        "unit"  => "amps",
                        "value" => 15,
                        'marketplace_id' => $marketplaceId
                    ]
                ],
                "current_rating"         => [
                    [
                        "unit"  => "amps",
                        "value" => 15,
                        'marketplace_id' => $marketplaceId
                    ]
                ],
                "auto_part_position"    => [
                    [
                        "value" => "front_right",
                        'marketplace_id' => $marketplaceId
                    ],
                    [
                        "value" => "front_left",
                        'marketplace_id' => $marketplaceId
                    ]
                ],
                "fastener"              => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "size" => [
                            [
                                "language_tag" => $language,
                                "value"        => 'M6' // Must match an allowed value from the enum list
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value"        => "Bolt" // Valid fastener type
                            ]
                        ]
                    ]
                ],
                "thread" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "coverage" => [
                            [
                                "value" => "Fully Threaded",
                                "language_tag" => "en_US"
                            ]
                        ],
                        "diameter" => [
                            [
                                "decimal_value" => 0.5,
                                "unit" => "thirty_seconds_inches"
                            ]
                        ],
                        "pitch" => [
                            [
                                "string_value" => "1.25",
                                "unit" => "centimeters"
                            ]
                        ],
                        "size" => [
                            [
                                "value" => "#2-56",
                                "language_tag" => "en_US"
                            ]
                        ],
                        "style" => [
                            [
                                "value" => "Right Hand",
                                "language_tag" => "en_US"
                            ]
                        ]
                    ]
                ],
                "grip"                  => [
                    [
                        "marketplace_id" => $marketplaceId, // Amazon US Marketplace
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value"        => "Textured" // Must match an allowed value from the enum list
                            ]
                        ]
                    ]
                ],
                "bulb"   => [
                    [
                        'marketplace_id' => $marketplaceId,
                        "base" => [
                            [
                                "language_tag"  => $language,
                                "value" => "b15d" // Bulb base type (must be in the predefined list)
                            ]
                        ]
                    ]
                ],
                "container"             => [
                    [
                        "type" => [
                            [
                                "language_tag"  => $language,
                                "value"         => $containerType[1]
                            ]
                        ],
                        "marketplace_id" => $marketplaceId
                    ]
                ],
                "handle" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value" => "Other"
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Other"
                            ]
                        ]
                    ]
                ],
                "blade" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "color" => [
                            [
                                "language_tag" => $language,
                                "value" => "Black" // ✅ Must be a valid color
                            ]
                        ],
                        "material" => [
                            [
                                "language_tag" => $language,
                                "value" => "Other"
                            ]
                        ],
                        "edge" => [
                            [
                                "language_tag" => $language,
                                "value" => "Serrated" // ✅ Must be one of the allowed types
                            ]
                        ],
                        "length" => [
                            [
                                "value" => 100.0,
                                "unit" => "millimeters"
                            ]
                        ],
                        "width" => [
                            [
                                "value" => 1.0,
                                "unit" => "inches"
                            ]
                        ],
                        "type" => [
                            [
                                "language_tag" => $language,
                                "value" => "Not Applicable"
                            ]
                        ]
                    ]
                ],
                "apparel_size" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "size_system" => "as1", // Ensure this is the correct value
                        "size_class" => "numeric",
                        "size" => "numeric_10",
                        "size_to" => "numeric_12",
                        "body_type" => "regular",
                        "height_type" => "tall"
                    ]
                ],
                "shirt_size" => [
                    [
                        "marketplace_id" => $marketplaceId,
                        "size_system" => "as1", // Ensure this is the correct value
                        "size_class" => "numeric",
                        "size" => "numeric_10",
                        "size_to" => "numeric_12",
                        "body_type" => "regular",
                        "height_type" => "tall"
                    ]
                ],
                'fulfillment_availability' => [[
                    'fulfillment_channel_code'      => 'DEFAULT',
                    'quantity'                      => 0,
                    "lead_time_to_ship_max_days"    => 30
                ]],

                // ASIN Suggestion
                "merchant_suggested_asin" => [[ "value" => $asin ]]
            ]
        ];

        return $data;
    }

    public function sanitizeString($input) {
        // Replace double quotes with ' inches'
        $input = str_replace('"', ' inches', $input);

        // Remove unwanted characters except letters, numbers, spaces, and dashes
        $sanitized = preg_replace('/[^A-Za-z0-9\- ]/', '', $input);

        // Normalize whitespace (remove extra spaces)
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized));

        return $sanitized;
    }

    private function formatUpc($value)
    {
        // If it's 13 digits, remove the first digit to make it 12
        return (strlen($value) === 13) ? substr($value, 1) : str_pad(substr($value, 0, 12), 12, '0', STR_PAD_LEFT);
    }

    private function formatEan($value)
    {
        // Ensure it's always 13 digits
        return (strlen($value) === 12) ? '0' . $value : $value;
    }

    private function formatGtin($value)
    {
        // Ensure it's always 14 digits
        if (strlen($value) === 12) {
            return '00' . $value;
        } elseif (strlen($value) === 13) {
            return '0' . $value;
        }
        return $value;
    }

    public function identifyBarcodeType($code)
    {
        $length = strlen($code);

        if ($length === 12) {
            return 'UPC';
        } elseif ($length === 13) {
            return 'EAN';
        } elseif ($length === 14) {
            return 'GTIN';
        }

        return 'Unknown';
    }

    public function containsUrl($string) {
        return preg_match('/\b(?:https?:\/\/|www\.)[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}\b/', $string) === 1;
    }

    public function removeExcludedWords($value)
    {
        $excludedWords = $this->phraseExcluded; // Add more words if needed
        return str_ireplace($excludedWords, '', $value);
    }

    public function truncate($value, $maxLength = 50)
    {
        return mb_strlen($value, 'UTF-8') > $maxLength
            ? mb_substr($value, 0, $maxLength, 'UTF-8')
            : $value;
    }

    function cleanItemName($string)
    {
        return trim(preg_replace('/[^A-Za-z0-9\s\-\_\&]/', '', $string));
    }
}