<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonDelistExclude extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amz_delist_exclude';
	protected $guarded = [];

	public $timestamps = true;

    public function getArray() {
        $excluded = $this->get();
        $array = array();
        foreach($excluded as $id) {
            $array[] = $id->sku;
        }
        return $array;
    }

    public function saveDelistExclude($sku) {
        $excluded		= App::make("AmazonDelistExclude");
        if($excluded->where(["sku" => $sku])->first()) return false;
        $excluded->sku	= $sku;
        $excluded->save();
        return true;
    }

    public function isExcluded($sku) {
        if(strlen($sku) < 6) {
            return false;
        }

        $prefixes = ["280", "406", "511", "512", "513"];
        foreach($prefixes as $pref) {
            $str = substr($sku,0,3);
            if($str == $pref) {
                return true;
            }
        }
        return false;
    }
}
