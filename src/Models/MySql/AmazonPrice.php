<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonPrice extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_prices';
	protected $guarded = [];

	public $timestamps = true;

	public function getSku()
	{
		return $this->sku ?? null;
	}

	public function getOwnPrice()
	{
		return $this->own_price ?? 0;
	}

	public function getMinPrice()
	{
		return $this->min_price ?? 0;
	}

	public function savePrice($sku, $market, $listingPrice, $currentPrice, $minPrice, $maxPrice, $mapPrice)
	{
		try {
			$prodData = $this->where('sku', $sku)->where('seller', $market)->first();
			if(!empty($prodData)) {
				$prodData->listing_price		= $listingPrice;
				$prodData->own_price			= $currentPrice;
				$prodData->min_price			= $minPrice;
				$prodData->max_price			= $maxPrice;
				$prodData->map_price			= $mapPrice;
				$prodData->ready_for_upload		= 1;
				return $prodData->update();
			}
			else {
				$prodData						= App::make("AmazonPrice");
				$prodData->sku					= $sku;
				$prodData->seller				= $market;
				$prodData->listing_price		= $listingPrice;
				$prodData->own_price			= $currentPrice;
				$prodData->min_price			= $minPrice;
				$prodData->max_price			= $maxPrice;
				$prodData->map_price			= $mapPrice;
				$prodData->ready_for_upload		= 1;
				return $prodData->save();
			}
		} catch(Exception $e) {
			echo "\nError Message:\t{$e->getMessage()}:\t At {$e->getFile()},\t line: {$e->getLine()}\n";
		}
	}
}
