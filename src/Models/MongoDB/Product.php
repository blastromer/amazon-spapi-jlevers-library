<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoTyphoeusBaseModel;
use Typhoeus\JleversSpapi\Models\MySql\UomOverride;
use Exception;

class Product extends MongoTyphoeusBaseModel
{
    /**
     * The database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mongodb_typhoeus_conn';

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection = 'products';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    public function getProductId()
    {
        return $this->productId;
    }

    public function getInventory()
    {
        return $this->inventory ?? [];
    }

    public function getAvailability()
    {
        return $this->inventory['availability'] ?? [];
    }

    public function getVendor($vendorName)
    {
        return $this->inventory['availability'][$vendorName] ?? [];
    }

    public function getBrand()
    {
        return $this->brand ?? null;
    }

    public function getMpn()
    {
        return $this->mpn ?? null;
    }

    public function getKeywords()
    {
        return $this->keywords ?? null;
    }

    public function getTitle()
    {
        return $this->title ?? null;
    }

    public function getDescription()
    {
        return $this->description ?? null;
    }

    public function getUpc()
    {
        return $this->upc ?? null;
    }

    public function getAmazon()
    {
        return $this->amazon ?? [];
    }

    public function getDimension()
    {
        return $this->dimensions ?? [];
    }

    public function getBuyLine() {
		return $this->buyLine ?? null;
	}

    public function getVendors()
    {
		return $this->inventory['availability'] ?? [];
	}

	public function getPricingPrice()
	{
		return $this->pricing['price'] ?? 0;
	}

	public function isChannelTrue($channel)
	{
		return $this->channels[$channel] ?? false;
	}

    public function getCost($vendor = null) {
		// $uomOverride = $uomOverride
		// 	?? appCache('UOMOverride')
		// 		->where('productId', '=', $this->productId)
		// 		->cacheTags($this->productId.'')
		// 		->first()
		// ;
		// $cost = xget($uomOverride, 'costPerUomQty', null);
		// if($cost !== null && ($vendor === null || $this->_isGoGreenVendor($vendor))) {
		// 	return $cost;
		// }
        // dd($this->pricing['cost']);
		if(!empty($vendor)) {
			$cost = $this->inventory['availability'][$vendor]['cost' ] ?? 0.00;
		}
		else {
			$cost = $this->pricing['cost'] ?? 0.00;
		}

		if(
			!empty($cost)//not 0, 0.0, '0', '0.0', '', or null. (Please note that negative numbers, including the strings '-0' and '-0.0' pass this check, and are not considered "empty")
			&& is_numeric($cost)
			&& !is_nan($cost)
		) {
			return $cost;
		}
        // dd($cost);
		return 0.00;
	}

    public function getMapMethod() {
		try {
			if(empty($this->pricing)){
				throw new Exception('product->pricing is empty');
			}
			if(!is_array($this->pricing)){
				throw new Exception('product->pricing is not an array as expected');
			}
			if(empty($this->pricing['mapMethod'])){
				throw new Exception('pricing[\'mapMethod\'] is empty');
			}
			if(!is_string($this->pricing['mapMethod'])){
				throw new Exception('pricing[\'mapMethod\'] is not a string');
			}

			return $this->pricing['mapMethod'];
		}catch(Exception $e) {
			return '';
		}
	}

    public function shipsThirdFreight() {//only for third party
		return (!empty($this->shipping['thirdFreightOnly'])) ? $this->shipping['thirdFreightOnly'] : false;
	}

    public function getMapPrice() {
		try {
			//these if blocks are for data sanitization to ensure the data is as expected
			if(empty($this->pricing)){
				throw new Exception('product->pricing is not an array as expected');
			}
			if(!is_array($this->pricing)){
				throw new Exception('product->pricing is not an array as expected');
			}
			if(empty($this->pricing['mapPrice'])){
				throw new Exception('pricing[\'mapPrice\'] is empty');
			}
			if(!is_numeric($this->pricing['mapPrice'])){
				throw new Exception('pricing[\'mapPrice\'] is not numeric');
			}
			if(is_nan($this->pricing['mapPrice'])){
				throw new Exception('pricing[\'mapPrice\'] is NaN');
			}
			if($this->pricing['mapPrice'] < 0.01){
				throw new Exception('pricing[\'mapPrice\'] less than a penny');
			}

			$mapPrice = $this->pricing['mapPrice'];

			return floatval(number_format($mapPrice, 2, '.', '')); //this rounds if it isn't rounded already.
		}catch(Exception $e) {
			return 0.00;
		}
	}

    public function shipsFlat() {
		return $this->shipping['flatRate'] ?? false;
	}

    public function shipsFreight()
    {
        $weight = $this->getProductShippingWeight();

        // Check if Orgill shipping is marked as freight
        if (!empty($this->orgill['freight']) && $this->orgill['freight'] === true) {
            return true;
        }

        // Check if shipping is explicitly marked as freight
        if (!empty($this->shipping['freight']) && $this->shipping['freight'] === true) {
            return true;
        }

        // Check weight condition for freight shipping
        if ($weight > 150 && !empty($this->shipping['free']) && $this->shipping['free'] === false) {
            return true;
        }

        // Check dimensional constraints for freight shipping
        if (
            ($this->getHeight() >= 180 || $this->getWidth() >= 180 || $this->getLength() >= 180) &&
            !empty($this->shipping['free']) && $this->shipping['free'] === false
        ) {
            return true;
        }

        // Check girth condition for freight shipping
        if ($this->getGirthValue() >= 160 && !empty($this->shipping['free']) && $this->shipping['free'] === false) {
            return true;
        }
        // dd(123123213);
        return false;
    }


    public function getProductShippingWeight() {
		$weight = 1.0;
		$dimensionWeight = 0.0;
		try {
			//get weight
			$weight = (float)$this->getWeight();
			//get dimensional weight
			$dimensionWeight = (float)$this->getDimensionalWeight();
		}
		catch(\Exception $e) {
			//TODO - rather than throwing an exception or simply moving on, this needs to
			//queue a notification/email for someone so the exception gets fixed
			// try{app('EmailError')->sendError($e);}catch(\Exception$ex){}//this needs to be queued
		}

		//return whichever is larger
		return $weight < $dimensionWeight ? $dimensionWeight : $weight;
	}

    public function getWeight() : float
    {
		$weight = 1.0;
		$uomQty = 1;
		try {
			$uomOverride = new UomOverride;
			$override = $uomOverride->where('productId', $this->getProductId())->first();
			$uomQty = $override->uomQty ?? 0;
			$overrideWeight = (float) $override->weightQty;

			if ($overrideWeight > 0.0) {
				//since this is an override, early exit if it's valid
				return (float)$overrideWeight * $uomQty;
			}

			$weight = (float) $this->dimensions['weight'] ?? 0.00;

			if($weight > 0.0) {
				return (float)$weight * $uomQty;
			}
		}
		catch(\Exception $e) {
			//TODO - rather than throwing an exception or simply moving on, this needs to
			//queue a notification/email for someone so the exception gets fixed
		}

		//if weight is still not valid, default to 1 lb
		if($weight <= 0.0) {
			$weight = 1.0;
		}

		return (float)$weight * $uomQty;
	}

    public function getRawDimensions() {
		return $this->dimensions;
	}

    public function getDimensionalWeight() {
		// Unlike weight, dimensional weight should default to 0 so that
		// we only use dimensional weight and show a notification about
		// it if it is greater than the weight
		$dim = 0.0;
		try {
			if(
				($height = $this->getHeight()) > 0.0
				&& ($width = $this->getWidth()) > 0.0
				&& ($length = $this->getLength()) > 0.0
			) {
				$DIMfactor = 194; //according to MikeG on 8.11, our current DIM factor from FedEx is 194 and from UPS is 200
				$packagingOverhead = 1.10;
				$dim = $height * $width * $length / $DIMfactor * $packagingOverhead;
			}
		}
		catch(\Exception $e) {
			//TODO - rather than throwing an exception or simply moving on, this needs to
			//queue a notification/email for someone so the exception gets fixed
			// try{app('EmailError')->sendError($e);}catch(\Exception$ex){}//this needs to be queued
		}
		return $dim > 0.0 ? (float)$dim : 0.0;
	}

    public function getLength() : float {
		return $this->getDimensions('lenght');
	}

	public function getWidth() : float {
		return $this->getDimensions('width');
	}

	public function getHeight() : float {
		return $this->getDimensions('height');
	}


    public function getDimensions($lengthWidthOrHeight)
    {
		try {
			$dimension = (float) $this->dimensions[$lengthWidthOrHeight] ?? 0;
			if($dimension !== 0.0) {
				return $dimension;
			}
		}
		catch(\Exception $e) {
			//TODO - rather than throwing an exception or simply moving on, this needs to
			//queue a notification/email for someone so the exception gets fixed
		}

		return 0.0;
	}

    private function getGirthValue() {
		$length = $this->getLength();
		$height = $this->getHeight();
		$width = $this->getWidth();

		$girth1 = $length + (2 * ($height + $width));
		$girth2 = $height + (2 * ($length + $width));
		$girth3 = $width + (2 * ($length + $height));

		if($girth1 > $girth2 && $girth1 > $girth3){
			return $girth1;
		}else if($girth2 > $girth1 && $girth2 > $girth3){
			return $girth2;
		}else {
			return $girth3;
		}
	}

    public function hasFlatPrice() {
		if(isset($this->shipping['flatRatePrice']) &&
		!is_nan($this->shipping['flatRatePrice']) &&
		is_numeric($this->shipping['flatRatePrice'])) {
			return true;
		}
		return false;
	}

    public function getFlatRatePrice() {
		try {
			$rate = 0;
			$flatRatePrice = $this->shipping['flatRatePrice'];
			if(
				$this->shipsFlat()
				&& !(
					empty($flatRatePrice)
					|| is_nan($flatRatePrice)
					|| !is_numeric($flatRatePrice)
					|| empty(floatval($flatRatePrice))
				)//flat price is not any invalid value
			) {
				$rate = floatval($flatRatePrice);
			}

			return $rate;
		}
		catch(Exception $e) {
			return 0;
		}
	}

    public function needsFoam() {
		if(!empty($this->shipping['foam']) && ($this->shipping['foam'] == true)) {
			return true;
		}
		return false;
	}

    public function hasInsurance()
    {
		return (empty($this->shipping['insurance']))? false : $this->shipping['insurance'];
	}

    public function isDeadstock() : bool {
		return ($this->getDeadstock() !== null);
	}

    public function getDeadstock() : ?string {
		return (empty($this->deadstock) || $this->deadstock === 'null') ? null : $this->deadstock;
	}

	public function getCategoryIds() {
		if(!empty($this->categories)) return $this->categories;
		else return [];
	}

	public function setFoam($value) {
		$this->foam = $value;
	}

    public function getListPrice() {
		try {
			//these if blocks are for data sanitization to ensure the data is as expected
			if(empty($this->pricing)){
				throw new Exception('product->pricing is not an array as expected');
			}
			if(!is_array($this->pricing)){
				throw new Exception('product->pricing is not an array as expected');
			}
			if(empty($this->pricing['listPrice'])){
				throw new Exception('pricing[\'listPrice\'] is empty');
			}
			if(!is_numeric($this->pricing['listPrice'])){
				throw new Exception('pricing[\'listPrice\'] is not numeric');
			}
			if(is_nan($this->pricing['listPrice'])){
				throw new Exception('pricing[\'listPrice\'] is NaN');
			}
			if($this->pricing['listPrice'] < 0.01){
				throw new Exception('pricing[\'listPrice\'] less than a penny');
			}
			if(is_object($this->pricing['listPrice'])){
				$list = floatval($this->pricing['listPrice']->__toString());
			}else {
				$list = $this->pricing['listPrice'];
			}

			$listPrice = $list;

			return floatval(number_format($listPrice, 2, '.', '')); //this rounds if it isn't rounded already.
		}
		catch(Exception $e) {
			return 0;
		}
	}
}
