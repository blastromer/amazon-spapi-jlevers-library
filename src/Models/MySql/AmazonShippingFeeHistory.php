<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonShippingFeeHistory extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amz_ship_fee';
	protected $guarded = [];
    public $timestamps = true;
    protected $primaryKey = 'id';

    public function savePrice($sku, $carrierType, $carrierMethod, $price) {
        try {
            $ship = $this->where('sku', $sku)->first();
            if(!empty($ship)) {
                if($ship->fee !== $price) {
                    $ship->carrier_type		= $carrierType;
                    $ship->carrier_method	= $carrierMethod;
                    $ship->fee				= $price;
                    $ship->update();
                    return true;
                }
                return false;
//					else {
//						dump("$sku has price");
//					}
            }
            else {
                $ship					= $this;
                $ship->sku				= $sku;
                $ship->fee				= $price;
                $ship->carrier_type		= $carrierType;
                $ship->carrier_method	= $carrierMethod;
                $ship->save();
                return true;
            }
        } catch(Exception $e) {
            dump("************");
            echo "\nError Message:\t{$e->getMessage()}:\t At {$e->getFile()},\t line: {$e->getLine()}\n";
            dd('test');
        }
    }

    public function getFee()
    {
        return $this->fee ?? null;
    }
}
