<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB\Logs;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class ProcessingListingError extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'spapi_processing_item_listing_errors';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
