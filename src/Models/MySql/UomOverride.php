<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlTyphoeusBaseModel;

class UomOverride extends MySqlTyphoeusBaseModel
{
	protected $database = 'typhoeus';
	protected $table = 'product_uom_override';
	protected $guarded = [];

	public $timestamps = true;
}
