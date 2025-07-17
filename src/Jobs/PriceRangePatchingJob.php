<?php

namespace Typhoeus\JleversSpapi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use SellingPartnerApi\Api\ListingsApi;
use SellingPartnerApi\Configuration;
use SellingPartnerApi\Endpoint;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Models\MongoDB\Jobs\JobMonitoring;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceReport;
use Carbon\Carbon;
use Exception;
use Log;

class PriceRangePatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $products;
    protected $jobMonitoringId;
    protected $uniqueJobName;
    protected $listing;

    public function __construct($products, $uniqueJobName, $jobMonitoringId)
    {
        $this->products = $products;
        $this->uniqueJobName = $uniqueJobName;
        $this->jobMonitoringId = $jobMonitoringId;
        $this->listing  = app(Listing::class);
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $appName        = $this->listing->app->getAppName();
        $count          = 0;
        $totalProducts  = count($this->products);
        $uniqueJobName  = class_basename(self::class) . "-" . Str::uuid();

        // Fetch Job Monitoring record
        $jobMonitoring = JobMonitoring::find($this->jobMonitoringId);

        $startTime = microtime(true);

        foreach ($this->products as $product) {
            $sku = $product->sku;

            try {
                $item = AmazonListing::where('sku', $sku)->first();

                if (!$item) {
                    $product->update(['ready_for_upload' => 0]);
                    Log::error("SKU: {$sku} NOT FOUND");
                    continue;
                }

                $ourPrice = $product->min_price;
                $value = [
                    [
                        "our_price" => [["schedule" => [["value_with_tax" => $ourPrice, "currency" => "USD"]]]],
                        "minimum_seller_allowed_price" => [["schedule" => [["value_with_tax" => $product->min_price, "currency" => "USD"]]]],
                        "maximum_seller_allowed_price" => [["schedule" => [["value_with_tax" => $product->max_price, "currency" => "USD"]]]],
                        "seller_fulfilled" => true
                    ]
                ];

                $productType = $item->product_type ?? null;

                if (strpos($productType, 'error') !== false) {
                    \Log::error("Invalid on SKU [{$sku}] with PRODUCT TYPE VALUE with [{$productType}]");
                    continue;
                }

                if ($item->price > 0 && $item->price == $ourPrice) {
                    $product->update(['ready_for_upload' => 0]);
                    Log::error("SKU: {$sku} has the same price, skipping.");
                    continue;
                }

                $submit = $this->listing->patchItem($sku, 'purchasable_offer', $value, $productType);

                if (isset($submit['error'])) {
                    AmazonPriceReport::create([
                        'sku'           => $sku,
                        'seller'        => $appName,
                        'status'        => $submit['status'] ?? 'UNKNOWN',
                        'is_failed'     => 1,
                        'error_message' => $submit['error']['message'] ?? 'No Error Message',
                        'min_price'     => $product->min_price,
                        'max_price'     => $product->max_price,
                    ]);
                    Log::error("SKU: {$sku} Error: " . $submit['error']['message']);
                } else {
                    if ($product->update(["ready_for_upload" => 0])) {
                        Log::info("SKU: {$sku} Updated successfully.");
                        $count++;
                    } else {
                        Log::error("SKU: {$sku} Failed to update.");
                    }
                }
            } catch (Exception $e) {
                Log::error("Error for SKU: {$sku} - {$e->getMessage()} at {$e->getFile()} line {$e->getLine()}");
            }

            // Update MongoDB with job progress
            $jobMonitoring->update([
                'message' => "Processing... {$count}/{$totalProducts} completed",
            ]);
        }

        // Calculate execution time
        $executionTime = microtime(true) - $startTime;

        // Final update to MongoDB
        $jobMonitoring->update([
            'status'        => 'completed',
            'message'       => "Job finished. Processed {$count}/{$totalProducts}",
            'execution_time'=> $executionTime,
        ]);

        Log::info("PriceRangePatchingJob completed in {$executionTime} seconds.");
    }
}