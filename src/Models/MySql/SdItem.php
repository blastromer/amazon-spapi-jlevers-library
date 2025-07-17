<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class SdItem extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'sd_items';
	protected $guarded = [];

	public $timestamps = false;
}
