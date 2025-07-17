<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonListing extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_listings';
	protected $guarded = [];

	public $timestamps = true;

	public function getSku()
	{
		return $this->sku ?? null;
	}

	public function getItemName()
	{
		return $this->item_name ?? null;
	}

	public function getItemDescription()
	{
		return $this->item_description ?? null;
	}

	public function getAsin()
	{
		return $this->asin ?? null;
	}

	public function getQty()
	{
		return $this->qty ?? 0;
	}

	public function getPrice()
	{
		return $this->price ?? 0;
	}

	public function getStatus()
	{
		return $this->status ?? null;
	}

	public function getProductType()
	{
		return $this->product_type ?? null;
	}
}
