<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class AmzSettings extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'settings';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
