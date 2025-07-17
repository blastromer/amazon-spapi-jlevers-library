<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonPriceLog extends MySqlShippingBaseModel

{

	protected $database = 'shipping';
	protected $table = 'amazon_price_logs';
	protected $guarded = [];
	protected $fillable = [
		'seller', 'sku', 'listing_price', 'own_price', 'min_price',
		'max_price', 'map_price', 'vendor', 'ship_fee', 'ship_type'
	];

	public $timestamps = true;

	public function getMinPrice()
	{
		return $this->min_price ?? null;
	}
}