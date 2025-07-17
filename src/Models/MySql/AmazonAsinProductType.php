<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonAsinProductType extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_asin_product_types';
	protected $guarded = [];

	public $timestamps = true;

	public function getProductType()
    {
        return $this->product_type ?? null;
    }
}
