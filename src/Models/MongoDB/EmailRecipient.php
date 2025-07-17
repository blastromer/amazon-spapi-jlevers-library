<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class EmailRecipient extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'email_recipients';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;
}
