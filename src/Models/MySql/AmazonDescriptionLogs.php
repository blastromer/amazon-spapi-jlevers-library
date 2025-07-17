<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonDescriptionLogs extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_description_logs';
	protected $guarded = [];

	public $timestamps = true;
}
