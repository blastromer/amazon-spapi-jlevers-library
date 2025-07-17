<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class CatalogItems extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'catalog_items';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
