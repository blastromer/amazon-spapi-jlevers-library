<?php

namespace Typhoeus\JleversSpapi\Helpers;

use Config;
use Typhoeus\JleversSpapi\Models\MongoDB\AmazonKitsMongo;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MongoDB\Category;
use Typhoeus\JleversSpapi\Models\MySql\ProductExclusionList;
use Typhoeus\JleversSpapi\Models\MySql\AmazonLossLogs;
use Typhoeus\JleversSpapi\Models\MySql\AmazonShippingFeeHistory;
use Typhoeus\JleversSpapi\Models\MySql\ShipmentHistory;
use Typhoeus\JleversSpapi\Models\MySql\PriceConfig;

class PriceHelper
{

	public $path;
	public $srcPath;
	public $reportPath;
	public $logsPath;
    public $product;
	public $website;

	public function __construct($website)
    {
		$this->website = $website;
	}

	public function getWebsite() {
		return $this->website;
	}

	public function getPriceExclusions() {
		$exclusion	= new ProductExclusionList;
		$array	= $exclusion->getArray();
		$excluded = array();
		foreach($array as $id => $settings) {
			if($settings["price"]) {
				$excluded[] = $id;
			}
		}
		return $excluded;
	}

	public function calculateInsurance($cost, $shipping, $avgGp) {
		$salePrice = (($cost * (1 + $avgGp)) + $shipping);

		if($salePrice < 100) {
			//insurance is free up to 100
			return 0.00;
		}
		elseif($salePrice < 300) {
			//insurance is $2.90 up to 300
			return 2.90;
		}
		else {
			//after $300, insurance is 0.95 per 100.
			$additionalToInsure = ($salePrice - 300);
			return (2.90 + ($additionalToInsure * .0095) );
		}
	}

	public function getKitCalculation($pid, $thirdParty, $forInsurance = false)
	{
        $amzKitMongo = new AmazonKitsMongo;
		$components = $amzKitMongo->getComponents($pid);
		$totalFee = 0;
		$skipFoam = 1;// set foam fee to $35
		foreach($components as $component) {
            $products = new Product;
			$product = $products->where('productId', (int) $component)->first();
			$shipFee = $this->getShippingFee($product, $thirdParty, $forInsurance, $skipFoam);
			$skipFoam = 2;// set foam fee to 0
			$totalFee += $shipFee["fee"];
		}
		return ["fee" => $totalFee, "type" => $shipFee["type"]];
	}

	public function getShippingFee(
        Product $product,
        bool $thirdParty,
        string $website,
        bool $forInsurance = false,
        bool $skipFoam = false
    ): array {
        // Handle kit-specific fee process
        if (!$skipFoam) {

            $pid = $product->getProductId();
            $kit = AmazonKitsMongo::where('productId', $pid)->first();

            if (!empty($kit)) {
                $kitFee = $this->getKitCalculation($pid, $thirdParty, $forInsurance);
                return [
                    "fee"  => round($kitFee["fee"], 2),
                    "type" => $kitFee["type"]
                ];
            }
        }

        // Determine foam cost (Using switch instead of match for PHP 7.3+ compatibility)
        switch ($skipFoam) {
            case 1:
                $foam = 35; // Default for Kit Set
                break;
            case 2:
                $foam = 0;
                break;
            default:
                $foam = 72; // Default for 1 Piece Toilet
        }

        // Determine third-party shipping increase
        $thirdPartyIncrease = ($website === "PO_Amazon") ? 0 : 0.40;
        // Fetch historical shipping data
        // $amazonProfitLoss = new AmazonLossLogs;
        // $deficit = $amazonProfitLoss->getPriceData($product->getProductId(), $this->getWebsite());

        $amzShippingFeeHistory = new AmazonShippingFeeHistory;

        // $amzHistoricalShip = !empty($deficit)
        //     ? $amzShippingFeeHistory->where('sku', $product->getProductId())->first()
        //     : null;

        // // Determine shipping fee based on available data
        // if (!empty($deficit) && !empty($amzHistoricalShip)) {
        //     $shipType = "Recalculated Amazon Historical";
        //     $shipping = $amzHistoricalShip->fee;
        // } elseif ($thirdParty && $product->shipsThirdFreight()) {
            // dump($product->shipsFreight());
            // dump($product->getFlatRatePrice());
        if ($thirdParty && $product->shipsThirdFreight()) {
            $shipType = "Third-Party Freight";
            $shipping = $this->getFlatRate($product, $thirdParty);

        } elseif ($product->shipsFlat() && $product->hasFlatPrice() && $product->getFlatRatePrice() > 0) {
            $shipType = "Flat Rate";
            $shipping = $product->getFlatRatePrice();

        } elseif ($this->specialFreeShipping($product)) {

            return ["fee" => 0.00, "type" => "Free Shipping"];

        } elseif ($product->shipsFreight()) {
            $shipType = "Freight";
            $shipping = $this->getFlatRate($product, $thirdParty);

        } else {
            $shippingHistory = new ShipmentHistory;

            $historicalFee = $shippingHistory->getHistoricalFee($product->getProductId());

            if (is_null($historicalFee)) {
                $historicalShip = $amzShippingFeeHistory->where('sku', $product->getProductId())->first();

				if(!$historicalShip) {
					$shipType = "Forced Flat Rate";
					$shipping = $this->getFlatRate($product, $thirdParty);
				}
				else {
					$shipType = "Amazon Historical";
					$shipping = $historicalShip->fee;
				}
            } else {
                $shipType = "Plumbersstock Historical";
                $shipping = $historicalFee;
            }
        }

        // Apply foam cost if needed
        if ($product->needsFoam() && $skipFoam) {
            $shipping += $foam;
            $product->setFoam($foam);
        } elseif ($product->needsFoam()) {
            foreach ($product->getCategoryIds() as $category) {
                if ($category->__toString() === "5a28870971273d36100a907c") {
                    $foam = 35; // 2 PIECE TOILET
                }
            }
            $shipping += $foam;
            $product->setFoam($foam);
        }

        // Calculate final shipping fee
        $calculatedShipping = round(
            $thirdParty ? ($shipping * (1 + $thirdPartyIncrease)) : $shipping,
            2
        );
        // dd($thirdParty);
        $setOnAmazon = ($product->getProductShippingWeight() == 0)
            ? 0
            : $this->getCurrentlySetShipping($product);

        $returnShipping = abs($calculatedShipping - $setOnAmazon);

        // if (!empty($deficit)) {
        //     $returnShipping += $deficit;
        // }
        // dd($returnShipping);
        return ["fee" => $returnShipping, "type" => $shipType];
    }

	public function specialFreeShipping($product) {
		try {
			if(empty($product->getBrand()) || strpos(strtolower($product->getBrand()), "pfister") === false) {
				return false;//throw new Exception("either brand not set, or does not qualify for this rule: pfister");
			}

			$avail = $product->getVendors();
			if (empty($avail)) return false;
			$swData = $avail['swplumbing'];
			$psData = $avail['plumbersstock'];
			// $pfmData = $avail['pfister_mldc'];
			// $pfeData = $avail['pfister_ecdc'];

			if(
				(
					(isset($swData['qty']) && isset($swData['cost']) && ($swData['cost'] > 0) && ($swData['qty'] <= 0)) ||
					(isset($psData['qty']) && isset($psData['cost']) && ($psData['cost'] > 0) && ($psData['qty'] <= 0))
				) &&
				(
					(isset($pfmData['qty']) && isset($pfmData['cost']) && ($pfmData['cost'] > 0) && ($pfmData['qty'] <= 0)) ||
					(isset($pfeData['qty']) && isset($pfeData['cost']) && ($pfeData['cost'] > 0) && ($pfeData['qty'] <= 0))
				)
			) {
				// if(!empty($this->logFile)) File::append($this->logFile, date("Y-m-d H:i:s") ."\t". $product->getProductId() ."\tFREE SHIPPING ON THIS PRODUCT according to rule: PFISTER\n");
				//this rule is applying to all pfister errantly. Also pfister is problematic now.
				return false;
			}
		} catch (Exception $e) {
			return false;
		}
	}

	public function getFlatRate($product, $thirdParty = false) {
		$isPoAmazon = ($this->getWebsite() === "PO_Amazon");
        // dump($product->getWeight());
		if($isPoAmazon) {
			$weight = $product->getWeight();
		}
		else {
			$weight = $product->getProductShippingWeight();
		}

		if((($thirdParty && $product->shipsThirdFreight()) || $product->shipsFreight()) && $weight > 0) {
			// $freight	= App::make("Freight");
			$zip		= 10001;//new york zip code
			$rate		= $this->calculateRate($zip, $weight);//(double)number_format(((85 + ($weight * 0.3)) * (1 + ((100000 - $zip) / 100000))), 2, '.', '');
			if($this->needsExtraPallet($product)) {
				$rate = $rate + 120;
			}
//			$rate = ( (($weight * 3) > 150) ? ($weight * 3) : 150); //set freight to weight * 3 if freight is set, if less than $150 (50 pounds), charge 150 for bulk
		}
		elseif($product->shipsFlat() && $product->getFlatRatePrice() > 0) {
			$rate = $product->getFlatRatePrice();
		}
		elseif (($weight > 0) && ($weight <= 1)) {
			$rate = 4.99;
		}
		else if (($weight > 1) && ($weight <= 2)) {
			$rate = 7.99;
		}
		else if (($weight > 2) && ($weight <= 5)) {
			$rate = ($isPoAmazon) ? 8.15 : 9.99;
		}
		else if (($weight > 5) && ($weight <= 10)) {
			$rate = ($isPoAmazon) ? 9.60 : 10.99;
		}
		else if (($weight > 10) && ($weight <= 15)) {
			$rate = ($isPoAmazon) ? 10.65 : 12.99;
		}
		else if (($weight > 15) && ($weight <= 20)) {
			$rate = ($isPoAmazon) ? 11.80 : 15.99;
		}
		else if (($weight > 20) && ($weight <= 25)) {
			$rate = ($isPoAmazon) ? 14.10 : 15.99;
		}
		else if (($weight > 25) && ($weight <= 30)) {
			$rate = ($isPoAmazon) ? 16.12 : 17.99;
		}
		else if (($weight > 30) && ($weight <= 35)) {
			$rate = ($isPoAmazon) ? 18.15 : 22.99;
		}
		else if (($weight > 35) && ($weight <= 40)) {
			$rate = ($isPoAmazon) ? 21.29 : 22.99;
		}
		else if (($weight > 40) && ($weight <= 45)) {
			$rate = ($isPoAmazon) ? 23.14 : 29.99;
		}
		else if (($weight > 45) && ($weight <= 50)) {
			$rate = ($isPoAmazon) ? 25.41 : 29.99;
		}
		else if (($weight > 50) && ($weight <= 70)) {
			$rate = ($isPoAmazon) ? 29.50 : 32.99;
		}
		else if (($weight > 70) && ($weight <= 100)) {
			$rate = 34.99;
		}
		else if (($weight > 100) && ($weight <= 150)) {
			$rate = 39.99;
		}
		else {
			$rate = 9.99;
		}
        // dd($rate);
		return $rate;
	}

	public function getCurrentlySetShipping($product) {
		$amazon = $product->getAmazon($this->website);
		if(
			isset($amazon['shipping']['ours']) &&
			!is_nan($amazon['shipping']['ours']) &&
			is_numeric($amazon['shipping']['ours']) &&
			($amazon['shipping']['ours'] >= 0.00)
		) {
			return floatval($amazon['shipping']['ours']);
		}
		else {
			return 0.00;
		}
	}

	public function getAmazonConfig($market) {
		try {
            $priceConfig = new PriceConfig;
			$config = $priceConfig->where("merchant", "=", $market)->first();
			if(empty($config)) {
				echo "No price configuration. Exiting...";
				// if(!empty($this->logFile)) File::append($this->logFile, date("Y-m-d H:i:s") ."\tNo price configuration. Exiting...");
				// App::make("Mailing")->toAdmin("Amazon Pricing Config", "Pricing process for marketplace [$market] as no price configuration.");
				exit;
			}

			$array = array(
				"lt2"		=> (object)array("min" => $config->getMinGP_lt2(), "max"	=> $config->getMaxGP_lt2(), "beatBy"	=> $config->getBeatBy_lt2()),
				"lt5"		=> (object)array("min" => $config->getMinGP_lt5(), "max"	=> $config->getMaxGP_lt5(), "beatBy"	=> $config->getBeatBy_lt5()),
				"lt20"		=> (object)array("min" => $config->getMinGP_lt20(), "max"	=> $config->getMaxGP_lt20(), "beatBy"	=> $config->getBeatBy_lt20()),
				"lt50"		=> (object)array("min" => $config->getMinGP_lt50(), "max"	=> $config->getMaxGP_lt50(), "beatBy"	=> $config->getBeatBy_lt50()),
				"lt100"		=> (object)array("min" => $config->getMinGP_lt100(), "max"	=> $config->getMaxGP_lt100(), "beatBy"	=> $config->getBeatBy_lt100()),
				"lt200"		=> (object)array("min" => $config->getMinGP_lt200(), "max"	=> $config->getMaxGP_lt200(), "beatBy"	=> $config->getBeatBy_lt200()),
				"lt500"		=> (object)array("min" => $config->getMinGP_lt500(), "max"	=> $config->getMaxGP_lt500(), "beatBy"	=> $config->getBeatBy_lt500()),
				"gt500"		=> (object)array("min" => $config->getMinGP_gt500(), "max"	=> $config->getMaxGP_gt500(), "beatBy"	=> $config->getBeatBy_gt500()),
				"packaging"	=> $config->getPackaging(),
				"foam"		=> $config->getFoam(),
			);
			return $array;
		}
		catch(Exception $e) {
			echo "Error:\t'{$e->getMessage()}':\t At {$e->getFile()},\t line: {$e->getLine()}\n";
		}
	}

	public function getPriceConfig($cost) {
		if		($cost < 2) 	$config = "lt2";
		elseif	($cost < 5)		$config = "lt5";
		elseif	($cost < 20)	$config = "lt20";
		elseif	($cost < 50)	$config = "lt50";
		elseif	($cost < 100)	$config = "lt100";
		elseif	($cost < 200)	$config = "lt200";
		elseif	($cost < 500)	$config = "lt500";
		else					$config = "gt500";

		return $config;
	}

	public function forceMap($product)
	{
		$buyLine 	= $product->getBuyline();
		$mapMethod 	= $product->getMapMethod();
		$mapPrice 	= $product->getMapPrice();

		if (!empty($mapMethod) && !empty($mapPrice) && $mapPrice > 0.00) {
			return floatval($mapPrice);
		}

		return false;
	}

	public function cleanSku($sku) {
		return intval(preg_replace("/[^\d]/", "", trim($sku)));
	}

    public static function calculateRate($zip, $weight, $vendor = null) {
		$weight		= ($weight < 150 ? 150 : $weight);
		$rate		= (double)number_format(((120 + ($weight * 0.3)) * (1 + ((100000 - $zip) / 100000))), 2, '.', '');
		if(isset($vendor) && $vendor != 'plumbersstock' && $vendor != 'swplumbing') {
			$rate = (double)number_format(($rate*1.6), 2, '.', '');
		}
		//HI 96701 - 96899 //AK 99501 - 99950
		$continentalUS = ((($zip > 96700 && $zip < 97000) || ($zip > 99500 && $zip < 100000) || ($zip >  599 && $zip < 1000)) ? false : true);
		if(!$continentalUS) {
			$rate = ($rate * 8); // 'freight_ak_hi' => 8,
		}
		return $rate;
	}

	public static function needsExtraPallet($product) {
		$limitA = 0;//any 2 side longer than 48 inches
		$limitB = 0;//all 3 sides longer than 40 inches

		foreach($product->getRawDimensions() as $dimension => $value) {
			if($dimension === "weight") continue;
			if($value > 96) return true;//if one side is greater than 96
			elseif($value > 48) $limitA++;
			elseif($value > 40) $limitB++;
		}
		if($limitA > 1) return true;
		if($limitB > 2) return true;

		return false;
	}
}