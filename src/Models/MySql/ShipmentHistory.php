<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;
use Exception;

class ShipmentHistory extends MySqlShippingBaseModel
{
    protected $database = 'shipping';
    protected $table = 'historical_1item_shipments';
    // protected $table = 'sd_shipments';
    public $timestamps = false;

    public function getHistoricalFee($productId) {
        $row = self::query()
        ->where(function ($query) {
            $query->whereRaw('UPPER(CarrierMethod) = ?', ['GND'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['UPS'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['UPSG'])
                  ->orWhereRaw('UPPER(CarrierMethod) LIKE ?', ['%GROUND'])
                  ->orWhereRaw('UPPER(CarrierMethod) LIKE ?', ['PRI%'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['FIRST'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['FHD'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['STD'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['EPD'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['US POSTAL'])
                  ->orWhereRaw('UPPER(CarrierMethod) = ?', ['PS BEST METHOD']);
        })
        ->where(function ($query) use ($productId) {
            $query->where('ProductId', $productId)
                  ->orWhere('ProductId', 'like', "%{$productId}%");
        })
        ->avg('ShippingExpense');

        return $row;
    }
}
