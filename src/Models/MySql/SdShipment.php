<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class SdShipment extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'sd_shipments';
	protected $guarded = [];

	public $timestamps = false;

    public function getEclipseId()
    {
        return $this->EclipseId;
    }

    public function getShippingCost()
    {
        return $this->ShippingExpense;
    }

    public function getCarrierType()
    {
        return $this->CarrierType;
    }

    public function getCarrierMethod()
    {
        return $this->ShippingExpense;
    }

    public function getProductId()
    {
        return $this->ProductId;
    }

    public function getQty()
    {
        return $this->Qty;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function getShippedDate()
    {
        return $this->created_at;
    }

    public function getBillTo()
    {
        return $this->BillTo;
    }
}
