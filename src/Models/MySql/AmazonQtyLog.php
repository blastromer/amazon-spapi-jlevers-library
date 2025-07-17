<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonQtyLog extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_qty_logs';
	protected $guarded = [];

	public $timestamps = true;
}
