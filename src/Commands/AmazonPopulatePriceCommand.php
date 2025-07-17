<?php

namespace Typhoeus\JleversSpapi\Commands;

use Exception;
use File;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQty;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceLog;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\KeyProperties;
use Typhoeus\JleversSpapi\Models\MySql\MarketConfig;
use Typhoeus\JleversSpapi\Models\MySql\ProductExclusionList;
use Typhoeus\JleversSpapi\Models\MySql\AmazonDelistExclude;
use Typhoeus\JleversSpapi\Models\MySql\BrandExclusion;
use Typhoeus\JleversSpapi\Models\MySql\AmazonFee;
use Typhoeus\JleversSpapi\Helpers\PriceHelper;
use Typhoeus\JleversSpapi\Helpers\Amazon;

class AmazonPopulatePriceCommand extends Command
{
	protected $description = 'This command is used to calculate and prepare price data to be patched';
	protected $signature  = 'amz-spapi-test:data-pricing:propagate
		{--create-log=false : This will store an information directly to Laravel Logs}
		{--show-message=false : This will show message after the TEST attemp}
		{--is-test=false : This will not save the changes whatever the sku is}
		{--notification= : This will send email message to owner}
		';

	private $logFile;

	public $path;
	public $srcPath;
	public $reportPath;
	public $logsPath;

	public $defaultVendor = ["plumbersstock", "swplumbing", "kentucky"];

	public function __construct(
        Listing $listing,
        AmazonQty $amazonQty,
        AmazonPrice $amazonPrice,
        AmazonPriceLog $amazonPriceLog,
        AmazonListing $amazonListing,
        Product $product,
        KeyProperties $keyProperties,
        MarketConfig $marketConfig,
        ProductExclusionList $productExclusionList,
        AmazonDelistExclude $amazonDelistExclude,
        BrandExclusion $brandExclusion,
        AmazonFee $amazonFee
    ) {
		parent::__construct();
        $this->listing = $listing;
        $this->amazonQty = $amazonQty;
        $this->amazonPrice = $amazonPrice;
        $this->amazonPriceLog = $amazonPriceLog;
        $this->amazonListing = $amazonListing;
        $this->product = $product;
        $this->keyProperties = $keyProperties;
        $this->marketConfig = $marketConfig;
        $this->productExclusionList = $productExclusionList;
        $this->amazonDelistExclude = $amazonDelistExclude;
        $this->brandExclusion = $brandExclusion;
        $this->amazonFee = $amazonFee;
	}

	public function handle()
	{
		$this->listing->setSellerConfig(true);

		$amzhelp		= new Amazon;
		$createLog		= filter_var($this->option("create-log"), FILTER_VALIDATE_BOOLEAN);
		$showMessage	= filter_var($this->option("show-message"), FILTER_VALIDATE_BOOLEAN);
		$isTest			= filter_var($this->option("is-test"), FILTER_VALIDATE_BOOLEAN);
		$testing		= false;
		$website		= $this->listing->app->getAppName();
		$prodArray		= ['373182po'];
		$site			= $this->keyProperties->where("WebSite", "=", $website)->first();

		if (empty($site)) {
			return;
		}

		$config = $this->marketConfig
			->where("webSite", "=", $site->WebSite)
			->first();

		$pricehelp	= new PriceHelper($site->WebSite);

		if (empty($config)) {
			$this->info("Cannot find Channel.\n---------------------------SKIP---------------------------\n\n");
			exit;//this means we have no idea what the channel for this site is.
		}

		$market			= $config->getMarket();
		$shipgroup		= $config->getMainRegShipGroup();
		$primeShip		= $config->getMainPrimeShipGroup();
		$mktRegBuffer	= $config->getMainRegBuffer();
		$mktPrimeBuffer	= $config->getMainPrimeBuffer();
		$secVendor		= $config->getSecondaryVendor();
		$secVendorArray = $config->getSecVendorArray();
		$secShipgroup	= $config->getSecRegShipGroup();
		$secPrimeShip	= $config->getSecPrimeShipGroup();
		$secRegBuffer	= $config->getSecRegBuffer();
		$secPrimeBuffer	= $config->getSecPrimeBuffer();

		$this->info("Channel: $market");
		$this->info("Buffer: $mktRegBuffer");

		if (!empty($secVendor)) {
			$this->info("Secondary Vendor: " . strtoupper($secVendor));
			$this->info("$secVendor Buffer: $secRegBuffer");
			$this->info("$secVendor Ship Group: $secShipgroup");
			$this->info("$secVendor Prime Shipping: $secPrimeShip\n\n");
		}

		$priceExclude	= $this->productExclusionList->getPriceExclusion();
		$excludeSkus	= $this->amazonDelistExclude->getArray();
		$brandExclude	= $this->brandExclusion;
		$amzQtyLeadtime	= $this->amazonQty;

		$list = $this->amazonListing
			->where("seller" , $site->WebSite)
			->where("is_skipped" , 0);

		if (!empty($prodArray)) {
			$list = $list
				->whereIn('sku', $prodArray);
		} else {
			$list = $list
				->where('qty', ">", 0);
		}

		$list = $list->get(["sku", "price"]);

		$this->info("Organizing Data...");

		$messageIdCount	= 0;
		$primeItemCount	= 0;
		$total			= count($list);
		$done			= 0;
		$begin			= Carbon::now();
		$deadDiscount	= 0.5;
		$discount		= 0;

		$this->info("Processing pricing update of [$total] products...");

		$progressbar = $this->output->createProgressBar($list->count());
		$progressbar->setFormat('very_verbose');
		$progressbar->start();

		foreach ($list as $amzProd) {
			$progressbar->advance();
			$priceData		= [];
			$sku			= $amzProd->getSku();
			$pid			= intval(preg_replace("/[^\d]/", "", trim($sku)));
			$secondary		= false;
			$thirdParty 	= false;
			$message		= "";
			$shippingFeeAmt	= 0;
			$insurance 		= 0;
			$queryPrice 	= [
				'is_skipped'	=> 0,
				'seller' 		=> $website,
				'sku' 			=> $sku
			];

			if (in_array($pid, $priceExclude)) { // excluded from pricing
				if ($createLog) {
					\Log::info("{$sku} price excluded. skip");
				}
				continue;
			}

			$availableData = $this->amazonQty
				->where($queryPrice)
				->first();

			$pricingData = $this->amazonPrice
				->where($queryPrice)
				->first();

			if (!$availableData) {
				if ($createLog) {
					\Log::info("{$sku} not listed. skip");
				}
				continue;
			}

			$vendor = $availableData->getVendor();

			if (strpos($sku, "-") !== false) { // no sku
				if ($createLog) {
					\Log::info("{$sku} no sku. skip");
				}
				continue;
			}

			if (in_array(($pid), $excludeSkus) || in_array($sku, $excludeSkus)) { // excluded from all processes
				if ($createLog) {
					\Log::info("{$sku} excluded. skip");
				}
				continue;
			}

			if ($config->isSecondary($sku)) { // setting up variables exclusive for kentucky
				$secondary		= true;
			}

			$product = $this->product // get mongo product data
				->where('productId', (int) $pid)
				->first();

			if (empty($product)) { // not in mongo anymore
				if ($createLog) {
					\Log::info("{$sku} not in mongo. skip");
				}
				continue;
			}

			if (!in_array($vendor, $this->defaultVendor) && !empty($vendor)) {
				$thirdParty = true;
			}

			$cost		= $product->getCost($vendor);
			$minPrice	= 0;
			$maxPrice	= 0;
			$mapPrice 	= $product->getMapPrice();
			$listPrice 	= $product->getListPrice();
			$shipFee	= $pricehelp->getShippingFee($product, $thirdParty, $site->WebSite);

			if ($cost <= 0.01) { // make a report that this product has no cost data
				continue;
			}

			if ($vendor == "stockmarket") {
				$cost = ($cost * 1.03);
			}

			if (!is_null($shipFee) && $shipFee != [] && !empty($shipFee)) {
				$shippingFeeAmt = isset($shipFee['fee']) ? $shipFee['fee'] : 0;
			}

			//amazon fee
			$amzFee		= $this->amazonFee->getAmzAdjustedFee($sku, $site->WebSite);

			if (strpos(strtolower($sku), "fba") !== false && $amzProd->price < $mapPrice) { // compare current price to map // FULFILLED BY AMAZON
				continue;
			}

			if ($site->WebSite === "PO_Amazon") { // min gross profit
				$minGp	= 0.15;
			} else {
				$minGp	= $pricehelp->getAmazonConfig($site->WebSite)[$pricehelp->getPriceConfig($cost)]->min / 100;
			}

			//max gross profit
			$maxGp		= $pricehelp->getAmazonConfig($site->WebSite)[$pricehelp->getPriceConfig($cost)]->max / 100;

			if ($product->hasInsurance()) { // insurance fee
				$insurance = $pricehelp->calculateInsurance($cost, $shipFee['fee'], (($maxGp + $minGp) / 2) );
			}

			if (!empty($amzhelp->checkDeadstock($product))) { // discount
				$minPrice	= (($cost * (1 - $deadDiscount)) + ($shipFee['fee'] + $insurance)) / (1 - $amzFee);
				$discount 	= ($cost - ($cost * $deadDiscount));
			} else {
				$minPrice	= (($cost / (1 - $minGp)) + ($shipFee['fee'] + $insurance)) / (1 - $amzFee);
				dump(1 - $amzFee);
				dump(($shipFee['fee'] + $insurance));
				dump($cost);
				dump($minGp);
				dd(($cost / (1 - $minGp)));
			}

			if ($site->WebSite === "PO_Amazon") { // compare to competition -> to follow
				$maxPrice	= $product->getListPrice() + ($shipFee['fee'] + $insurance);
			} else {
				$maxPrice	= ((($cost / (1 - $maxGp)) + ($shipFee['fee'] + $insurance)) / (1 - $amzFee));
			}

			if ($amzhelp->isMap($product) && $mapPrice > $minPrice) { // compare to map
				if ($site->WebSite === "Cricut_Amazon") {
					$minPrice	= $mapPrice;
					$maxPrice	= $minPrice * 3;
				} else {
					$minPrice	= $mapPrice;
					$maxPrice	= $minPrice * 1.5;
				}
			}

			if ($minPrice * 1.5 > $maxPrice) { // making sure min and max have the appropriate gap
				if ($site->WebSite === "Cricut_Amazon") {
					$maxPrice = $minPrice * 3;
				} else {
					$maxPrice = $minPrice * 1.5;
				}
			}

			if ($minPrice < 6.67) { // amazon fee is the greater of 15% or $1.00. ($1 is roughly 15% of $6.67)
				$wrongFee	= ($minPrice * $amzFee);
				$diff		= (1.00 - $wrongFee);
				$minPrice	+= $diff;
				$maxPrice	+= $diff;
			}

			$minPrice = round($minPrice, 2);
			$maxPrice = round($maxPrice, 2);

			if ($pricehelp->forceMap($product)) { // If MAP Method is set to HARD
				$finalMAP = $mapPrice + ($shippingFeeAmt + $insurance);

				if ($cost >= $finalMAP) { // If the MAP is Less than Cost
					$pricingCost 	= $product->getPricingPrice(); // Pulling Pricing Price
					$finalMAP 		= $pricingCost + $shippingFeeAmt; // Adding New Pricing plus shipping fee
				}

				if ($minPrice > $finalMAP) { // If the MAP is lesser than compare to the calculated Minimum Price
					$finalMAP = $minPrice; // Override MAP with Minimum Price
				}

				$minPrice = round($finalMAP, 2);
				$maxPrice = round($maxPrice, 2);

				if ($minPrice >= $maxPrice) {
					$maxPrice = round(($minPrice * 1.5), 2);
				}

				if ($pricingData->getMinPrice() === $minPrice) { // Skip if Min Price is the same
					continue;
				}

				$priceData = [
					'listing_price' 	=> $listPrice,
					'own_price' 		=> $amzProd->getPrice(),
					'min_price'			=> $minPrice,
					'max_price'			=> $maxPrice,
					'map_price' 		=> $mapPrice,
					'ready_for_upload' 	=> 1
				];
				dd($priceData);
				if ($isTest) {
					continue;
				}

				$dataResult = $this->amazonPrice // Updating or Creating in Amazon Price
					->updateOrCreate($queryPrice, $priceData);

				if ($dataResult->getChanges()) { // Saving to Amazon Price Logs
					$changes = $dataResult->getChanges() ?? [];

					if (isset($changes['min_price'])) {
						unset($priceData['ready_for_upload']);

						$skuValue 				= $testing ? $sku . '_testing' : $sku;
						$priceData['seller']    = $site->WebSite;
						$priceData['sku']   	= $skuValue;
						$priceData['vendor']	= $vendor;
						$priceData['ship_fee']	= $shippingFeeAmt;
						$priceData['ship_type']	= 'HARD MAP, No calculation';

						$this->amazonPriceLog->create($priceData);
						\Log::info($priceData);
					}
				}
				$messageIdCount++;
				$done++;

				continue;
			}

			if ($showMessage) {
				$this->showTestPrice(
					$sku,
					$vendor,
					$cost,
					$insurance,
					$shippingFeeAmt,
					$minPrice,
					$maxPrice,
					$amzFee,
					$minGp,
					$discount,
					$shipFee['type'],
					$product->getDeadstock()
				);
			}

			if ($pricingData->getMinPrice() == $minPrice) {
				continue;
			}

			$priceData = [
				'listing_price' 	=> $listPrice,
				'own_price' 		=> $amzProd->getPrice(),
				'min_price'			=> $minPrice,
				'max_price'			=> $maxPrice,
				'map_price' 		=> $mapPrice,
				'ready_for_upload' 	=> 1
			];

			if ($isTest) {
				continue;
			}

			$dataResult = $this->amazonPrice
				->updateOrCreate($queryPrice, $priceData);

			if ($dataResult->getChanges()) {
				$changes = $dataResult->getChanges() ?? [];

				if (isset($changes['min_price'])) {
					unset($priceData['ready_for_upload']);

					$skuValue 				= $testing ? $sku . '_testing' : $sku;
					$priceData['seller']    = $site->WebSite;
					$priceData['sku']   	= $skuValue;
					$priceData['vendor']	= $vendor;
					$priceData['ship_fee']	= $shippingFeeAmt;
					$priceData['ship_type']	= $shipFee['type'];

					$this->amazonPriceLog->create($priceData);
					\Log::info($priceData);
				}
			}

			$done++;
			$messageIdCount++;
		}

		$progressbar->finish();
		$fullTime = number_format(((time() - $begin->timestamp) / 60), 2, ".", ",");
		$this->info("\n------------------------------END------------------------------\n");
		$this->info(date("Y-m-d H:i:s") ." processed [$done] products and has updated [$messageIdCount] in $fullTime minutes.\n\n");
	}

	public function showTestPrice(
		$sku,
		$vendor,
		$cost,
		$insurance,
		$shippingFeeAmt,
		$minPrice,
		$maxPrice,
		$amzFee,
		$minGp,
		$discount,
		$shipFeeType,
		$deadstock
	) {
		$message		= "";

		$message .= "\n----" .$sku . "----\n";
		$message .= "vendor ". $vendor . "\n";
		$message .= "cost " . $cost . "\n";
		$message .= "insurance " . $insurance . "\n";
		$message .= "shipping fee " . $shippingFeeAmt . "\n";
		$message .= "amazon Fee  $minPrice * $amzFee = " . $minPrice * $amzFee . "\n";
		$message .= "min gp " . (($cost / (1 - $minGp)) - $cost) . "\n";
		$message .= "discount -" . $discount . "\n";
		$message .= "ship type " . $shipFeeType . "\n";
		$message .= $deadstock . "\n";
		$message .= "min price formula (($cost / (1 - $minGp)) + ({$shippingFeeAmt} + $insurance)) / (1 - $amzFee) = " . $minPrice . "\n";
		$message .= "min price variables added: cost $cost + insurance $insurance + shipping fee {$shippingFeeAmt} + amazon fee " . ($minPrice * $amzFee) . " + min gp ". (($cost / (1 - $minGp)) - $cost) . " - $discount =" . "\n";
		$message .= "min price variables total " . ($cost + $insurance + $shippingFeeAmt + ($minPrice * $amzFee) + (($cost / (1 - $minGp)) - $cost) - $discount) . "\n";
		$message .= "final min price $minPrice" . "\n";
		$message .= "final max price $maxPrice" . "\n";

		$this->info($message);
	}
}