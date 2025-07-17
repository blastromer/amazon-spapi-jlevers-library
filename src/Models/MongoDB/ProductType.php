<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class ProductType extends MongoAmazonSpBaseModel
{
	protected $database     = 'amazon_sp';
	protected $collection   = 'product_types';
	protected $guarded      = [];

	public $timestamps      = true;
	public $incrementing    = true;

    public function getProductTypeName()
    {
        return $this->name;
    }
}
