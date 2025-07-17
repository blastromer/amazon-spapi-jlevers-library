<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class Scheduler extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'scheduler';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
