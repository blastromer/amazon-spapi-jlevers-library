<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class MerchantShippingGroup extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'merchant_shipping_group';
	protected $guarded = [];

	public $timestamps = true;

	
	public function getTokenArray() {
		$data = $this->select("merchant_shipping_group_name", "merchant_shipping_group_hash")->get()->toArray();
		$merch = array();
		foreach($data as $token) {
			$merch[$token["merchant_shipping_group_name"]] = $token["merchant_shipping_group_hash"];
		}
		return $merch;
	}
}