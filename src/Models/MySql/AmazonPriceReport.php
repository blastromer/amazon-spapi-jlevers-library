<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonPriceReport extends MySqlShippingBaseModel

{

	protected $database = 'shipping';

	protected $table = 'amazon_pricing_report';

	protected $primaryKey = 'id';

	public $timestamps = true;

}