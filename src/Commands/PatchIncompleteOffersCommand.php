<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use \Carbon\Carbon as Carbon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice as AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceReport as AmazonPriceReport;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;

class PatchIncompleteOffersCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:patch:incomplete-listing';
    protected $description = 'This command will patch or change the price range of our products';

    protected $listing;

    public function __construct(
		Listing $listing,
		AmazonListing $amazonListing,
        AmazonPrice $amazonPrice
	) {
        parent::__construct();
        $this->listing = $listing;
		$this->amazonListing = $amazonListing;
        $this->amazonPrice = $amazonPrice;
    }

	public function handle()
	{
		$this->listing->setSellerConfig(true);
		$appName 		= $this->listing->app->getAppName();
		$amzPrice		= new AmazonPrice;
		$amzPriceReport	= new AmazonPriceReport;
		$hours = 96;//how long ago was the data updated
		$products = $this->amazonListing->where('status', 'Incomplete')
				->where('seller', $this->listing->app->getAppName())
				// ->where('updated_at', '>=', Carbon::now()->subMinutes($hours*60)->toDateTimeString())//we need fresh data
				->get();//->take(1000);
        // dd($products->count());
		if(empty(count($products))) $this->info("Warning! No update available for the last 12 hours!");
		$progressbar = $this->output->createProgressBar(count($products));
		$progressbar->start();

		$count	= 0;
		$begin	= Carbon::now();
		// dd($products->count());
		$progressbar        = new ProgressBar(new Console(), $products->count());
        $progressbar->setFormat('Patching Price Offers %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressbar->start();
		foreach($products as $product) {
			$progressbar->advance();
			$sku    =  $product->sku;
			$item   = $this->amazonPrice->where('sku', $sku)->first();
            // dd($item);
			// This skip's when found nothing from Amazon Listing
			if (!$item) {
				// $product->update(['ready_for_upload' => 0]);
                dump("Found nothing from SKU [{$sku}]");
				continue;
			}
			try {
                $ownPrice = number_format($item->min_price + ($item->min_price * 0.1), 2);
				$value = [
					[
                        "currency" => "USD",
						"our_price" => [
							[
								"schedule" => [
									[
										// "value_with_tax" => ($product->max_price - (0.10)),
										"value_with_tax" => $ownPrice
									]
								]
							]
						],
						"minimum_seller_allowed_price" => [
							[
								"schedule" => [
									[
										"value_with_tax" => $item->min_price
									]
								]
							]
						],
						"maximum_seller_allowed_price" => [
							[
								"schedule" => [
									[
										"value_with_tax" => $item->max_price
									]
								]
							]
						],
						"seller_fulfilled" => true
					]
				];
                $values = [
                    [
                        "value" => $ownPrice,
                        "currency" => 'USD'
                    ]
                ];

				// $report = $amzPriceReport
				// 		->where('seller', $this->listing->app->getAppName())
				// 		->where('sku', $product->sku)
				// 		->first();
				$productType = $product->product_type ?? null;
                // dd($productType);

				// if ($item->price == $product->own_price) {
				// 	// dump($product->own_price);
				// 	// dump($item->price);
				// 	// dd($product->sku);
				// 	$product->update(['ready_for_upload' => 0]);
				// 	continue;
				// } else {
				$submit = $this->listing->patchItem($product->sku, $attr = 'purchasable_offer', $value, $productType);
				// $submit = $this->listing->patchItem($product->sku, $attr = 'list_price', $value, $productType);
				// }
				// dump($product->sku);
				// continue;
                // dump($sku);
                // dump($value);
                // dd($submit);

				if (isset($submit['error'])) {
					dump($submit);
					// foreach ($submit->issues as $issue) {
                    //     $errArrayData           = json_decode(json_encode($issue), true);
                    //     $errArrayData['sku']    = $product->getSku();
                    //     $this->processingListingError->create($errArrayData);
                    //     $this->error("Error on SKU [{$product->getSku()}] with a message: [{$issue->message}]");
                    //     if ($issue->message == "The Amazon product type specified is invalid or not supported.") {
                    //         $product->update(['is_skipped' => 1]);
                    //     }
                    // }
					// $priceReport				= new AmazonPriceReport;
					// $priceReport->sku			= $product->sku;
					// $priceReport->seller		= $this->listing->app->getAppName();
					// $priceReport->status		= ($submit == true) ? "ACCEPTED" : $submit->getStatus();
					// $priceReport->is_failed		= 0;
					// $priceReport->error_message	= $submit['error']['message'] ?? 'No Error Message';
					// $priceReport->min_price		= $product->min_price;
					// $priceReport->max_price		= $product->max_price;
					// $priceReport->save();
				}

				// if(is_array($submit)) continue;//skipped because it's not in amazon anymore

				if((!is_array($submit) && $submit->getStatus() == "ACCEPTED") || $submit == true) {
					if(!empty($report)) {
						// $report->status				= ($submit == true) ? "ACCEPTED" : $submit->getStatus();
						// $report->is_failed			= 0;
						// $report->error_message		= ($submit == true) ? "" : $submit->getIssues()[0]->getMessage();
						// $report->min_price			= $product->min_price;
						// $report->max_price			= $product->max_price;
						// $report->update();
					}
					else {
						// $priceReport				= new AmazonPriceReport;
						// $priceReport->sku			= $product->sku;
						// $priceReport->seller		= $this->listing->app->getAppName();
						// $priceReport->status		= ($submit == true) ? "ACCEPTED" : $submit->getStatus();
						// $priceReport->is_failed		= 0;
						// $priceReport->error_message	= ($submit == true) ? "" : $submit->getIssues()[0]->getMessage();
						// $priceReport->min_price		= $product->min_price;
						// $priceReport->max_price		= $product->max_price;
						// $priceReport->save();
					}

					// if($product->update(["ready_for_upload" => 0])) {
					// 	$count++;
					// }
					// else {
					// 	$this->error("[{$product->sku}] Failed to Update!");
					// }
				}
				else {
					// if(!empty($report)) {
					// 	$report->status				= ($submit == true) ? "REJECTED" : $submit->getStatus();;
					// 	$report->is_failed			= 1;
					// 	$report->error_message		= $submit->getIssues()[0]->getMessage();
					// 	$report->min_price			= $product->min_price;
					// 	$report->max_price			= $product->max_price;
					// 	$report->update();
					// }
					// else {
					// 	$priceReport				= new AmazonPriceReport;
					// 	$priceReport->sku			= $product->sku;
					// 	$priceReport->seller		= $this->listing->app->getAppName();
					// 	$priceReport->status		= ($submit == true) ? "REJECTED" : $submit->getStatus();;
					// 	$priceReport->is_failed		= 1;
					// 	$priceReport->error_message	= $submit->getIssues()[0]->getMessage();
					// 	$priceReport->min_price		= $product->min_price;
					// 	$priceReport->max_price		= $product->max_price;
					// 	$priceReport->save();
					// }
				}
			}
			catch(Exception $e) {
				$this->info("Error Found:\t'{$e->getMessage()}':\t At {$e->getFile()},\t line: {$e->getLine()}\n");
			}
		}
		$progressbar->finish();
		$this->info("Total prices processed: [$count]");
		$fullTime = number_format(((Carbon::now()->timestamp - $begin->timestamp) / 60), 2, ".", ",");
		$this->info("Took: $fullTime minutes to process this marketplace.");
	}
}