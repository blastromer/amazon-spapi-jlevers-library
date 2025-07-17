<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class KeyProperties extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'key_properties';
	protected $guarded = [];

	public $timestamps = true;
}
