<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonFee extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amz_fee';
	protected $guarded = [];
    protected $primaryKey = 'sku';
	public $timestamps = true;

    public function saveFeeData($sku, $market, $fee) {
        try {
            $amzFee						= $this->where("market", $market)->where("sku", $sku)->first();
            if(empty($amzFee)) {
                $amzFee					= $this;
                $amzFee->sku			= $sku;
                $amzFee->market			= $market;
                $amzFee->fee_percent	= $fee;
                $amzFee->save();
                return true;
            }
            elseif((float)$amzFee->fee_percent !== (float)$fee) {
                $amzFee->fee_percent	= $fee;
                $amzFee->update();
                return true;
            }

            return false;
        } catch(Exception $e) {
            dump("************");
            echo "\nError Message:\t{$e->getMessage()}:\t At {$e->getFile()},\t line: {$e->getLine()}\n";
            return false;
        }
    }

    public function getAmzAdjustedFee($sku, $market) {
        $amzFee	= $this->where("market", $market)->where("sku", $sku)->first();
        if(!empty($amzFee->fee_percent) && $amzFee->fee_percent > 15) {
            return $amzFee->fee_percent / 100;
        }
        return 0.15;

    }

    public function getAmzRealFee($sku, $market) {
        $amzFee	= $this->where("market", $market)->where("sku", $sku)->first();
        if(!empty($amzFee->fee_percent)) {
            return $amzFee->fee_percent / 100;
        }
        return 0.15;

    }
}
