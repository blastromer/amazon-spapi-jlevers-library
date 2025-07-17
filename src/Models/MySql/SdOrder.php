<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class SdOrder extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'sd_orders';
	protected $guarded = [];

	public $timestamps = false;
}
