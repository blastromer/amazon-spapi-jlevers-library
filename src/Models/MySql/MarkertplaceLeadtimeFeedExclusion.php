<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class MarkertplaceLeadtimeFeedExclusion extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'marketplace_leadtime_feed_exclusion';
	protected $guarded = [];

	public $timestamps = true;

}
