<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class CatalogItemAsin extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'catalog_item_asin';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
