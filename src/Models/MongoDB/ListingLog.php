<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class ListingLog extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'listing_logs';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
