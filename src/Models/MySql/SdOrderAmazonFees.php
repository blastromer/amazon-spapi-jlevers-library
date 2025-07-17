<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class SdOrderAmazonFees extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'sd_orders_amazon_fees';
	protected $guarded = [];

	public $timestamps = true;
}
