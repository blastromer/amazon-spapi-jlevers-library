<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Models\MongoDB\TyphoeusProduct;
use Typhoeus\JleversSpapi\Helpers\Amazon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonParentSku;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQty;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;

class AmazonInventoryFromMongoToMysqlShippingCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:collate-inventory {--s|seller=}';
    protected $description  = 'Collate amazon inventory from mongo to mysql shipping command.';
    private $log_info = '';

    public function __construct(
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $process_start = Carbon::now()->toDateTimeString();

        try {
            $seller = $this->option('seller');
            $amazon = new Amazon();
            $seller_info = $amazon->checkSeller($seller);

            $this->log_info = "Command:" . $this->signature . "\n\n";

            if (is_null($seller_info)) {
                throw new Exception('Seller (' . $seller . ") does not exists!\n");
            }

            $loop_bool = true;
            $number_of_records = 100;
            $channels = $seller_info->channel;
            $warehouses = $seller_info->channel_inventory;
            $current_page = 392;

            do {
                $rows = AmazonParentSku::whereSeller($seller_info->website)->orderBy('sku', 'ASC')->paginate($number_of_records, ['*'], 'page', $current_page);

                $total_page = $rows->lastPage();

                if ($current_page > $total_page) {
                    $loop_bool = false; // Kill loop
                }

                foreach ($rows as $row) {

                    try {

                        #$this->log_info .= $row->sku . "\n";

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
                                    ->first(['productId', 'inventory', 'channels', 'amazon.asin']);

                        if (is_null($product)) {
                            throw new Exception("Product record not exists!\n");
                        }

                        foreach ($warehouses as $key_wh => $value_wh) {

                            $sku = $row->sku . '' . $key_wh;
                            #$this->log_info .= $sku . ' ';

                            if (!isset( $product->inventory['availability'][$value_wh])) {
                                throw new Exception("No inventory field\n");
                            } elseif (!isset($product->inventory['availability'][$value_wh]['qty'])) {
                                throw new Exception("No warehouse field ($value_wh)\n");
                            }

                            $qty = $product->inventory['availability'][$value_wh]['qty'];
                            #$this->log_info .= $this->saveAction($sku, $qty, $seller_info->website) . " Qty = $qty\n";
                        } // foreach ($warehouses as $key_wh => $value_wh) {

                    } catch (Exception $e) {
                        $this->log_info .= ' ' . json_encode($e->getMessage()) . "\n";
                    }
                    dump($row->sku);
                } // foreach ($rows as $row) {

                dump($current_page);
                $current_page++;

            } while($loop_bool);

        } catch (Exception $e) {
            $this->log_info .= ' ' . json_encode($e->getMessage());
        }

        $process_end = Carbon::now()->toDateTimeString();

        $this->info("Process Start:\t$process_start\nProcess End:\t$process_end\n" . $this->log_info);
    }

    private function saveAction($sku, $qty, $website) {

        $row = AmazonQty::whereSeller($website)->where('sku', $sku)->first();
        $listing = AmazonListing::whereSeller($website)->where('sku', $sku)->first();

        $listing_qty = 0;

        if(!is_null($listing)) {
            $listing_qty = $listing->qty;
        }

        if(is_null($row)) {

            AmazonQty::create([
                'seller' => $website,
                'sku' => $sku,
                'qty' => $qty,
                'qty_prev' => $qty,
                'ready_for_upload' => 1,
            ]);

            return 'Insert';

        } elseif ($qty != $listing_qty) {

            $row->seller = $website;
            $row->qty = $qty;
            $row->qty_prev = $row->qty;
            $row->ready_for_upload = 1;
            $row->save();

            return 'Update';

        } else {

            $row->ready_for_upload = 0;
            $row->save();

            return 'No Changes';
        }
    }
}
