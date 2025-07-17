<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class ProductAttribute extends MongoAmazonSpBaseModel
{
	protected $database     = 'amazon_sp';
	protected $collection   = 'product_attributes';
	protected $guarded      = [];
    protected $fillable     = ['attributes', 'category'];

	public $timestamps      = true;
	public $incrementing    = true;

    public function getAttributes()
    {
        return $this->attributes ?? [];
    }
}
