<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Services\Pricing;
use Typhoeus\JleversSpapi\Services\Feed;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class GetListingByAsinCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature    = 'amz-spapi:catalog:listing:asin';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;
    public $amazonQualifying;

    public function __construct(
        Listing $listing,
        Pricing $pricing,
        Feed $feed,
        Catalog $catalog,
        AmazonQualifying $amazonQualifying,
        Product $product
    ) {
        parent::__construct();
        $this->listing  = $listing;
        $this->pricing  = $pricing;
        $this->feed     = $feed;
        $this->catalog  = $catalog;
        $this->amazonQualifying  = $amazonQualifying;
        $this->product  = $product;
    }

    public function handle()
    {
        $this->info('Test Get Listing by ASIN...');
        $this->catalog->setSellerConfig(true);
        $this->listing->setSellerConfig(true);
        // $listByAsin = $this->catalog->getCatalogByASIN(['B0B1Y9BM9C']);
        $products = $this->amazonQualifying->get();
        $bar        = new ProgressBar(new Console(), $products->count());
        $bar->setFormat('Updating Qualified Table %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        $i = 1;
        foreach ($products as $product) {
            // $response = $this->catalog->getCatalogItemByASIN($product->getAsin());
            // $productType = $response[];
            // $productType = $this->listing->getProductDetails($product->getSku(), 'product_type');
            // dump($response->getProductTypes());
            $bar->advance();
            $productId = str_replace(['po', 'kw'], '', $product->getSku());
            // dd($productId);
            $productMongo    = $this->product->where('productId', (int) $productId)->first();
            if ($productMongo) {
                $amazon = $productMongo->getAmazon() ?? [];
                $missing = $this->updateMissing($product->getSku());
                $newData = [
                    'product_type' => 'TOOLS',
                    'product_group' => null,
                    'publisher' => null,
                    'studio' => null,
                    'package_qty' => 1
                ];
                if ($missing != []) {
                    $newData =  $missing;
                }
                // dd($newData);
                if ($amazon != []) {
                    $content = isset($amazon['content']) ? $amazon['content'] : [];
                    $attribute = isset($content['attributes']) ? $content['attributes'] : [];

                    $productType    = isset($attribute['ProductTypeName']) ? $attribute['ProductTypeName']
                                    : isset($newData['product_type']) ? $newData['product_type']
                                    : 'TOOLS';
                    $productGroup   = isset($attribute['ProductGroup']) ? $attribute['ProductGroup']
                                    : isset($newData["product_group"]) ? $newData["product_group"]
                                    : null;
                    $publisher      = isset($attribute["Publisher"]) ? $attribute["Publisher"]
                                    : isset($newData["publisher"]) ? $newData["publisher"]
                                    : null;
                    $studio         = isset($attribute["Studio"]) ? $attribute["Studio"]
                                    : isset($newData["studio"]) ? $newData["studio"]
                                    : null;
                    $packageQty     = isset($attribute["PackageQuantity"]) ? $attribute["PackageQuantity"]
                                    : isset($newData["package_qty"]) ? $newData["package_qty"]
                                    : 1;

                    $updatedFields = [];

                    if (is_null($product->product_group)) {
                        $updatedFields['product_group'] = $productGroup;
                    }
                    if (is_null($product->product_type)) {
                        $updatedFields['product_type'] = $productType;
                    }
                    if (is_null($product->publisher)) {
                        $updatedFields['publisher'] = $publisher;
                    }
                    if (is_null($product->studio)) {
                        $updatedFields['studio'] = $studio;
                    }
                    if (is_null($product->package_qty)) {
                        $updatedFields['package_qty'] = $packageQty;
                    }

                    // Update only if there are changes
                    if (!empty($updatedFields)) {
                        $product->update($updatedFields);
                    }
                } else {
                    // dump($i++);
                }
            }

        }
        $bar->finish();

        $this->info("[DONE]");
        $this->info("Updated successfully...");
    }

    public function updateMissing($sku)
    {
        $productId = str_replace(['kw', 'po'], '', $sku);
        $products = $this->amazonQualifying
            ->where('sku', 'LIKE', '%'. $productId . '%')
            ->orderByDesc('id')
            ->get();
        if ($products->count() < 2) {
            return [];
        }
        return $products->first()->toArray() ?? [];
    }
}
