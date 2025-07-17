<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonMerchantShippingGroup extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_merchant_shipping_group';
	protected $guarded = [];

	public $timestamps = true;

}
