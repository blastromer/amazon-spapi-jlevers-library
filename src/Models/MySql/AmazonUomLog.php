<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonUomLog extends MySqlShippingBaseModel

{

	protected $database = 'shipping';

	protected $table = 'amazon_uom_log';

	protected $guarded = [];

	public $timestamps = true;

}