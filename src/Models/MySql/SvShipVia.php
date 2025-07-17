<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class SvShipVia extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'sv_ship_via';
	protected $guarded = [];

	public $timestamps = true;
}
