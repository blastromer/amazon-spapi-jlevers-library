<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Illuminate\Support\Facades\Storage;

class PatchBulletPointsCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:patch:bullet-points';
    protected $description = 'This command will patch or change the specific field';

    protected $listing;
    public $vendorInitial = [
        'po' => 'plumbersstock',
        'kw' => 'kentucky'
    ];
    public $defaultVendor       = ['po', 'kw'];
    public $primaryVendors      = ['plumbersstock'];
    public $secondaryVendors    = ['kentucky'];
    public $buffer              = 0;

    public function __construct(
        Listing $listing,
        Product $products,
        Catalog $catalog
    ) {
        parent::__construct();
        $this->listing = $listing;
        $this->products = $products;
        $this->catalog = $catalog;
    }

    public function handle()
    {
        // searchAsinByItemName
        $this->info('Adding new Listings Item...');
        $this->listing->setSellerConfig(true);
        $this->catalog->setSellerConfig(true);
        $sellertId      = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $sku = "581688kw";
        $title = "Rolling Craft Bag - Rolling Tote Bag & Scrapbook Storage Organizer for Craft Machines & Supplies, Canvas Trolley - Blue with Multi-Color Chevron";
        $description = "CGull 10-0016 - 360 Rolling Ultimate Machine Scrapbooking Tote (Chevron). The multi-colored chevron patterned is paired with a navy-colored material that is sure to delight your eyeballs.";
        $responseSearch = $this->catalog->searchAsinByItemName($title);
        $items = $responseSearch->getItems();
        // dd($responseSearch);
        foreach ($items as $item) {
            dump($item);
        }
        dd();
        $bulletPoints = [
            [
                "value" => 'Spacious & Organized Storage: 9 pockets, including 5 removable clear pouches (1 large, 4 small), perfect for organizing tools, office supplies, school essentials, and craft materials. Outer dimensions: 23” x 20” x 10”; inner dimensions: 22” x 18” x 9”'
            ],
            [
                "value" => 'Ideal for Crafters: Fits Cricut Maker, Cricut Explore Air, Silhouette Cameo 3, or Sizzix Big Shot, plus laptop, materials, cartridges, and tools.'
            ],
            [
                "value" => 'Smart Organization: Side pockets hold cartridges/embellishments, front/back pockets fit 12” x 12” paper, and a large cubby stores your cutting machine.'
            ],
            [
                "value" => 'Durable & Travel-Friendly: Heavy-duty polyester fabric for tear resistance.'
            ],
            [
                "value" => '360 degree Swivel Wheels: Smooth-rolling, pivoting wheels for easy transport.'
            ]
        ];
        // dd($bulletPoints);
        // $response = $this->listing->patchItem("581688kw", $attr = 'item_name', $value = [['value' => $title,  "language_tag" => "en_US", "marketplace_id" => $marketplaceId]]); // working
        // $response = $this->listing->patchItem("581688", $attr = 'bullet_point', $value = $bulletPoints); // working
        $response = $this->listing->patchItem("581688kw", $attr = 'item_name', $value = [['value' => $title,  "language_tag" => "en_US"]]);
        // $data = [
        //     'product_type'  => empty($productType) ? null : $productType,
        //     'requirements'  => 'LISTING',
        //     'attributes'    => [
        //         "merchant_suggested_asin"   => [[ "value" => 'B06XDVF66R' ]],
        //         "item_name"                 => [[ "value" => $title, "language_tag" => "en_US", "marketplace_id" => $marketplaceId ]],
        //     ]
        // ];
        // $offersData = [
        //     // [
        //     //     "marketplace_id"    => "ATVPDKIKX0DER",
        //     //     "offer_type"        => "B2C",
        //     //     "price" => [
        //     //         "amount"                    => (float) $offerPrice,
        //     //         "is_fulfilled_by_amazon"    => false
        //     //     ]
        //     // ]
        // ];
        // $response = $this->listing->uploadListing($sku, $data, $offersData, 'A1ROZDTKM3L1EG', ['ATVPDKIKX0DER']);
        dump($response);
        // "item_name"             => [[ "value" => $title, "language_tag" => $language, "marketplace_id" => $marketplaceId ]],
        // "bullet_point"          => $bulletPoints,
        // patchItem($sku, $attr = 'fulfillment_availability', $value = [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => 0]])
        // $response = $this->listing->putListing();
        // dump($response);
    }
}