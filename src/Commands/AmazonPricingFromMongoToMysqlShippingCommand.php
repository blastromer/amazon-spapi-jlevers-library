<?php

namespace Typhoeus\JleversSpapi\Commands;

use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Models\MongoDB\TyphoeusProduct;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonParentSku;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceLog;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyLog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;

class AmazonPricingFromMongoToMysqlShippingCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:collate-pricing {--s|seller=}';
    protected $description  = 'Collate amazon pricing from mongo to mysql shipping command.';
    private $log_info = '';

    public function __construct(
    ) {
        parent::__construct();
    }

    public function handle()
    {

        try {
            $seller = $this->option('seller');
            $amazon = new Amazon();
            $seller_info = $amazon->checkSeller($seller);

            $this->log_info = "Command:" . $this->signature . "\n\n";

            if (is_null($seller_info)) {
                throw new Exception('Seller (' . $seller . ") does not exists!\n");
            }

            $channels = $seller_info->channel;
            $warehouses = $seller_info->channel_inventory;
            $rows = AmazonParentSku::get();

            foreach ($rows as $row) {
                try {

                    $this->log_info .= $row->sku . "\n";

                    $product = TyphoeusProduct::where('productId', intval($row->sku))
                                ->where(function($query) use ($channels){
                                    $n = 0;
                                    foreach ($channels as $channel) {
                                        if ($n == 0) {
                                            $query->where('channels.' . $channel, true);
                                        } else {
                                            $query->orwhere('channels.' . $channel, true);
                                        }
                                        $n++;
                                    }
                                })
                                ->first(['productId', 'inventory', 'channels', 'amazon']);

                    if (is_null($product)) {
                        throw new Exception("Product record not exists!\n");
                    }
                    
                    foreach ($warehouses as $key_wh => $value_wh) {

                        $sku = $row->sku . '' . $key_wh;
                        $this->log_info .= $sku . ' ';

                        if (!isset( $product->inventory['availability'][$value_wh])) {
                            throw new Exception("No inventory field\n");
                        } elseif (!isset($product->inventory['availability'][$value_wh]['price'])) {
                            throw new Exception("No price field ($value_wh)\n");
                        }

                        $price = $product->amazon[$seller_info->price];

                        $listing_price = $product->inventory['availability'][$value_wh]['price'];
                        $own_price = $price['pricing']['ours'];
                        $min_price = $price['price_range']['min'];
                        $max_price = $price['price_range']['max'];
                        $map_price = $price['pricing']['mapPrice'];

                        $this->log_info .= $this->saveAction($seller_info->website, $sku, $listing_price, $own_price, $min_price, $max_price, $map_price) . "\n";
                    } // foreach ($warehouses as $key_wh => $value_wh) {

                } catch (Exception $e) {
                    $this->log_info .= ' ' . json_encode($e->getMessage()) . "\n";
                }
            } // foreach ($rows as $row) {

        } catch (Exception $e) {
            $this->log_info .= ' ' . json_encode($e->getMessage());
        }

        $this->info($this->log_info);
    }

    private function saveAction($website, $sku, $listing_price, $own_price, $min_price, $max_price, $map_price) {

        $row = AmazonPrice::where('seller', $website)->where('sku', $sku)->orderBy('created_at', 'DESC')->first();

        if(is_null($row)) {
            AmazonPrice::create([
                'seller' => $website,
                'sku' => $sku,
                'listing_price' => $listing_price,
                'own_price' => $own_price,
                'min_price' => $min_price,
                'max_price' => $max_price,
                'map_price' => $map_price,
                'ready_for_upload' => 1,
            ]);

            return 'Insert';

        } elseif ($listing_price != $row->listing_price || $own_price != $row->own_price || $min_price != $row->min_price || $max_price != $row->max_price || $map_price != $row->map_price) {

            $row->seller = $website;
            $row->listing_price = $listing_price;
            $row->own_price = $own_price;
            $row->min_price = $min_price;
            $row->max_price = $max_price;
            $row->map_price = $map_price;
            $row->ready_for_upload = 1;
            $row->save();

            return 'Update';

        } else {

            $row->seller = $website;
            $row->ready_for_upload = 0;
            $row->save();

            return 'No Changes';
        }
    }
}
