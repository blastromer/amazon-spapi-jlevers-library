<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;
use Typhoeus\JleversSpapi\Jobs\PriceRangePatchingJob;
use Typhoeus\JleversSpapi\Models\MongoDB\Jobs\JobMonitoring;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceReport;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Carbon\Carbon;
use Exception;

class AmazonPriceRangePatchCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:upload:price-range
		{--method=NORMAL : Select method to patch purchasable_offer (NORMAL or JOB)}
		{--missing-offer : Patch only incomplete items in the catalog}';

    protected $description = 'This command will patch or change the price range of our products';

    protected $listing;
    protected $amazonListing;

    public function __construct(Listing $listing, AmazonListing $amazonListing)
    {
        parent::__construct();
        $this->listing = $listing;
        $this->amazonListing = $amazonListing;
    }

    public function handle()
    {
        $this->listing->setSellerConfig(true);
        $appName = $this->listing->app->getAppName();
        $method = strtoupper($this->option('method'));

        if (!in_array($method, ['NORMAL', 'JOB'])) {
            $this->error("Invalid method. Use either NORMAL or JOB.");
            return;
        }

        $this->info("Selected method: {$method}");

		// Job Processing
        if ($method === 'JOB') {
            $this->info("Dispatching Job...");

            AmazonPrice::where('ready_for_upload', 1)
                ->where('seller', $appName)
				->where('is_skipped', 0)
                // ->whereBetween('id', [111000, 111010])
                ->chunk(500, function ($products) use ($appName) {
					$uniqueJobName = class_basename(PriceRangePatchingJob::class) . "-" . Str::uuid();

					// Create Job Monitoring record for this batch
					$jobMonitoring = JobMonitoring::create([
						'seller' => $appName,  // Add your seller name or dynamically fetch it
						'job_name' => $uniqueJobName,
						'status' => 'processing',
						'message' => 'Job started',
						'execution_time' => null,
						'created_at' => Carbon::now(),
					]);

                    // Dispatch each batch to the queue
                    PriceRangePatchingJob::dispatch($products, $jobMonitoring, $jobMonitoring->id)->onQueue('high');
                });

            $this->info("All jobs have been dispatched to the queue.");
            return;
        }

        // Normal Processing
        $this->info("Processing Normal...");
        $products = AmazonPrice::where('ready_for_upload', 1)
			->where('is_skipped', 0)
            ->where('seller', $appName)
            ->get();

        if ($products->isEmpty()) {
            $this->info("Warning! No update available for the last 12 hours!");
            return;
        }

        $progressbar = $this->output->createProgressBar($products->count());
        $progressbar->setFormat('Patching Price Offers %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressbar->start();

        $count = 0;
        $begin = Carbon::now();

        foreach ($products as $product) {
            $progressbar->advance();
            $sku            = $product->sku;
            $missingOffer   = $this->option('missing-offer') ? ['status' => 'Incomplete'] : [];
            $query          = $this->amazonListing->where('sku', $sku);

            if (!empty($missingOffer)) {
                $query->whereRaw("status = 'Incomplete'");
            }

            $item = $query->first();

            if (!$item || $product->min_price == $product->own_price) {
                if (!$this->option('missing-offer')) {
                    $product->update(['ready_for_upload' => 0]);
                }
                continue;
            }

            if ($this->option('missing-offer') && $item->price > 0 && $item->qty <= 0) {
                continue;
            }

            try {
                $ourPrice = $product->min_price;
                $value = [
                    [
                        "our_price" => [
                            [
                                "schedule" => [
                                    [
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

                if (strpos($productType, 'error') !== false) {
                    $this->error("Invalid on SKU [{$sku}] with PRODUCT TYPE VALUE with [{$productType}]");
                    continue;
                }

                if ($item->price > 0 && $item->price == $ourPrice) {
                    $product->update(['ready_for_upload' => 0]);
                    continue;
                } else {
                    $submit = $this->listing->patchItem($product->sku, 'purchasable_offer', $value, $productType);
                }

                if (isset($submit['error'])) {
                    AmazonPriceReport::create([
                        'sku'            => $product->sku,
                        'seller'         => $appName,
                        'status'         => $submit == true ? "ACCEPTED" : $submit->getStatus(),
                        'is_failed'      => 0,
                        'error_message'  => $submit['error']['message'] ?? 'No Error Message',
                        'min_price'      => $product->min_price,
                        'max_price'      => $product->max_price,
                    ]);

                    \Log::error($submit['error']);
                } else {
                    if ($product->update(["ready_for_upload" => 0])) {
                        $count++;
                    } else {
                        $this->error("[{$product->sku}] Failed to Update!");
                    }
                }
            } catch (Exception $e) {
                $this->error("Error Found: '{$e->getMessage()}': At {$e->getFile()}, line: {$e->getLine()}");
				\Log::error("Error Found: '{$e->getMessage()}': At {$e->getFile()}, line: {$e->getLine()}");
            }
        }

        $progressbar->finish();
        $this->info("\nTotal prices processed: [$count]");
        $fullTime = number_format(((Carbon::now()->timestamp - $begin->timestamp) / 60), 2, ".", ",");
        $this->info("Took: $fullTime minutes to process this marketplace.");
    }
}
