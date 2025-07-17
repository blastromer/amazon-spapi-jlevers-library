<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonCompetitivePrice extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_competitive_prices';
	protected $guarded = [];

	public $timestamps = true;

	public function getLanded()
	{
		return $this->landed_price ?? false;
	}
}
