<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonQtyBufferLog extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_qty_buffer_logs';
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
		return $this->qty;
	}

}
