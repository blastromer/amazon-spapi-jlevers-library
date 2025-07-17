<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;
use Carbon\Carbon;

class AmazonLossLogs extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amz_profit_loss';
	protected $guarded = [];

	public $timestamps = true;

    public function savePriceLoss($sku, $market, $price, $loss, $shipType, $test = false) {
        try {
            $prodData	= $this->where('sku', $sku)->where('market', $market)->first();
            $today		= Carbon::today()->toDateTimeLocalString();
//				dump("$sku, $market, $price, $loss");
//				return;

            if(!empty($prodData)) {
                $prodData->price		= $price;
                $prodData->loss			= $loss;
                $prodData->ship_type	= $shipType;
                $prodData->updated_at	= $today;
                if(!$test) $prodData->update();
            }
            else {
                $prodData				= $this;
                $prodData->sku			= $sku;
                $prodData->market		= $market;
                $prodData->price		= $price;
                $prodData->loss			= $loss;
                $prodData->ship_type	= $shipType;
                $prodData->updated_at	= $today;
                if(!$test) $prodData->save();
            }
        } catch(Exception $e) {
            dump("************");
            echo "\nError Message:\t{$e->getMessage()}:\t At {$e->getFile()},\t line: {$e->getLine()}\n";
            dd('test');
        }
    }

    public function getPriceData($sku, $market) {
        $prodData = $this->where('sku', $sku)->where('market', $market)->first();
        if(empty($prodData)) return 0.00;

        if(Carbon::today()->diffInDays($prodData->updated_at) <= 7) return abs($prodData->loss);
        else return 0.00;
    }
}
