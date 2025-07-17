<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

class BrandExclusion extends MySqlShippingBaseModel
{
	protected $database = 'shipping';
	protected $table = 'amz_map_brand_exclusion';
	protected $guarded = [];

	public $timestamps = true;

    public function getBrands() {
		$array = [];
		$all = $this->get();
		foreach($all as $item) {
			$array[] = $item->brand;
		}
		return $array;
	}

	public function isExcludedBrand($product) {
		$brands		= $this->getBrands();
		$title		= $product->getTitle();
		$desc		= $product->getDescription();
		$priceline	= $product->getPriceLine();
		$buyline	= $product->getBuyLine();
		$prodBrand	= $product->getBrand();
		foreach($brands as $brand) {
			if(strpos(strtolower($title), $brand) !== false) {
				return true;
			}
			elseif(strpos(strtolower($desc), $brand) !== false) {
				return true;
			}
			elseif(strpos(strtolower($priceline), $brand) !== false) {
				return true;
			}
			elseif(strpos(strtolower($buyline), $brand) !== false) {
				return true;
			}
			elseif($prodBrand === $brand) {
				return true;
			}
		}
		return false;
	}
}
