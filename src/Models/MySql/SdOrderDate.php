<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class SdOrderDate extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'sd_order_dates';
	protected $guarded = [];

	public $timestamps = false;
}
