<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonParentSku extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_parent_skus';
	protected $guarded = [];

	public $timestamps = true;
}
