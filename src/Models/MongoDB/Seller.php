<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class Seller extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'sellers';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
