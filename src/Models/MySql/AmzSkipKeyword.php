<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmzSkipKeyword extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amz_skip_keyword';
	protected $guarded = [];

	public $timestamps = true;
}
