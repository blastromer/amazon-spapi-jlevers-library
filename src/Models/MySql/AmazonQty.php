<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonQty extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_qty';
	protected $guarded = [];

	public $timestamps = true;

	public function getSeller()
	{
		return $this->seller ?? null;
	}

	public function getSku()
	{
		return $this->sku ?? null;
	}

	public function getQty()
	{
		return $this->qty ?? 0;
	}

	public function getVendor()
	{
		return $this->vendor ?? null;
	}
}
