<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class SchedulerLogItem extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'scheduler_log_items';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
