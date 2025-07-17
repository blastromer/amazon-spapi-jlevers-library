<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonDescription extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_description';
	protected $guarded = [];
	protected $fillable = ['ready_for_upload'];

	public $timestamps = true;
}
