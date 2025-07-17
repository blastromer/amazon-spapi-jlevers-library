<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlProductBaseModel;

class ProductExclusionList extends MySqlProductBaseModel
{
	protected $database = 'products';
	protected $table = 'prod_exclusion_List';
	public $timestamps = false;

    public function getArray() {
		$all = $this->get();
		$array = array();
		foreach($all as $id) {
			if($id->hasExpired()) continue;//if expired, we ignore the exclude settings. The product updates automatically like normal
			$array[$id->getProductId()] = ["price" => $id->excludePrice(), "qty" => $id->excludeQty()];
		}
		return $array;
	}

	public function getPriceExclusion() {
		$array	= $this->getArray();
		$return = array();
		foreach($array as $id => $settings) {
			if($settings["price"]) {
				$return[] = $id;
			}
		}
		return $return;
	}

	public function getProductId()
	{
		return $this->pid ?? null;
	}

	public function hasExpired() {
		$expires = strtotime($this->expires);
		$now = time();
		return ($expires < $now);//is it past expiry date?
	}

	public function excludePrice() {
		//if 1 in database, we want to exclude updates (NOT UPDATE PRICE IF 1 in DB)
		//return empty($this->price);
		return (bool)$this->price ?? false;
	}

	public function excludeQty() {
		//if 1 in database, we want to exclude updates (NOT UPDATE QTY IF 1 in DB)
		//return empty($this->qty);
		return (bool)$this->qty ?? false;
	}

	public function getProductTitle()
	{
		return $this->title ?? null;
	}

	public function getDateUntil()
	{
		return $this->expires ?? null;
	}
}