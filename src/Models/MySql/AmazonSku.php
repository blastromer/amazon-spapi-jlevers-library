<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonSku extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_sku';
	protected $guarded = [];

	public $timestamps = true;
}
