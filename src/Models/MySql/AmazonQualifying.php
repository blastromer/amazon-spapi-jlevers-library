<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class AmazonQualifying extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amazon_qualifying_items';
	protected $guarded = [];

	public $timestamps = true;

    public function getSeller()
    {
        return $this->seller;
    }

    public function getSku()
    {
        return $this->sku;
    }

    public function getAsin()
    {
        return $this->asin;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getModelNumber()
    {
        return $this->model_number;
    }

    public function getPartNumber()
    {
        return $this->part_number;
    }

    public function getBrand()
    {
        return $this->brand;
    }

    public function getProductGroup()
    {
        return $this->product_group;
    }

    public function getProductType()
    {
        return is_null($this->product_type) ? 'PRODUCT' : $this->product_type;
    }

    public function getPublisher()
    {
        return $this->publisher;
    }

    public function getStudio()
    {
        return $this->studio;
    }

    public function getPackageQty()
    {
        return $this->package_qty;
    }

    public function getUpc()
    {
        return $this->upc;
    }

    public function getIsUploaded()
    {
        return $this->is_uploaded;
    }

    public function getIsSkipped()
    {
        return $this->is_skipped;
    }
}
