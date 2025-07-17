<?php

namespace Typhoeus\JleversSpapi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SellingPartnerApi\Api\ListingsApi;
use SellingPartnerApi\Configuration;
use SellingPartnerApi\Endpoint;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceReport;
use Exception;
use Log;

class ListingItemUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $products;
    protected $listing;

    public function __construct($products)
    {
        $this->products = $products;
        $this->listing  = app(Listing::class);
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
		$appName    = $this->listing->app->getAppName();
        $count = 1;
        // \Log::info($this->products->count());
        foreach ($this->products as $product) {
            $sku =  $product->sku;
			$missingOffer = [];

			$item = AmazonListing::where('sku', $sku)->first();
			// This skip's when found nothing from Amazon Listing
			if (!$item) {
				$product->update(['ready_for_upload' => 0]);
                Log::error($item . ". NOT FOUND THIS SKU");
				continue;
			}

			try {
				// $ourPrice = number_format($product->min_price + ($product->min_price * 0.1), 2);
				$ourPrice = $product->min_price;
				$value = [
					[
						"our_price" => [
							[
								"schedule" => [
									[
										// "value_with_tax" => ($product->max_price - (0.10)),
										"value_with_tax" => $ourPrice,
										"currency" => "USD"
									]
								]
							]
						],
						"minimum_seller_allowed_price" => [
							[
								"schedule" => [
									[
										"value_with_tax" => $product->min_price,
										"currency" => "USD"
									]
								]
							]
						],
						"maximum_seller_allowed_price" => [
							[
								"schedule" => [
									[
										"value_with_tax" => $product->max_price,
										"currency" => "USD"
									]
								]
							]
						],
						"seller_fulfilled" => true
					]
				];

				$productType = $item->product_type ?? null;

				if ($item->price > 0 && $item->price == $ourPrice) {
					$product->update(['ready_for_upload' => 0]);
                    Log::error($item->id . ". this SKU has 0 price");
					continue;
				} else {
					$submit = $this->listing->patchItem($product->sku, $attr = 'purchasable_offer', $value, $productType);
				}

				if (isset($submit['error'])) {
					AmazonPriceReport::create([
                        'sku'            => $product->sku,
                        'seller'         => $appName,
                        'status'         => $submit['status'] ?? 'UNKNOWN',
                        'is_failed'      => 1,
                        'error_message'  => $submit['error']['message'] ?? 'No Error Message',
                        'min_price'      => $product->min_price,
                        'max_price'      => $product->max_price,
                    ]);
					Log::error($submit['error']);
				} else {
					if($product->update(["ready_for_upload" => 0])) {
                        Log::info($submit);
						$count++;
					}
					else {
						Log::error("[{$product->sku}] Failed to Update!");
					}
				}
			}
			catch(Exception $e) {
				Log::info("Error Found:\t'{$e->getMessage()}':\t At {$e->getFile()},\t line: {$e->getLine()}\n");
			}
        }
        // \Log::info($this->products->count());
    }
}