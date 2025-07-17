<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQty as AmazonQty;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyLog as AmazonQtyLog;
use Typhoeus\JleversSpapi\Models\MySql\AmazonListing;
use Typhoeus\JleversSpapi\Models\MySql\MarkertplaceLeadtimeFeedExclusion as LeadtimeExclusion;
use Config;
use \Carbon\Carbon as Carbon;

class AmazonQtyLeadPatchCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:upload:qty';
    protected $description = 'This command will patch or change the inventory and leadtime of our products';

    protected $listing;

	public $byPassLeadtime = ['swplumbing' => 5];

    public function __construct(
		Product $product,
		Listing $listing,
		AmazonListing $amazonListing,
		AmazonQty $amazonQty,
		LeadtimeExclusion $leadtimeExclusion
	) {
        parent::__construct();
        $this->product 				= $product;
        $this->listing 				= $listing;
        $this->amazonListing 		= $amazonListing;
        $this->amazonQty 			= $amazonQty;
        $this->leadtimeExclusion 	= $leadtimeExclusion;
    }

	public function handle()
	{
		$this->listing->setSellerConfig(true);
		$seller		= $this->listing->app->getAppName();
		$counter	= 0;
		$begin		= Carbon::now();
		$channel	= $this->listing->getConfigChannel($this->listing->app->getAppName());

		$products = $this->amazonQty
			->where('ready_for_upload', 1)
			->where('is_skipped', 0)
			->where('seller', $seller)
			->where('qty', '>=', 0)
			->orderBy('id', 'asc')
			->get();

		if ($products->count() <= 0) {
			return;
		}

		$progressbar = new ProgressBar(new Console(), $products->count());
        $progressbar->setFormat('Processing %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressbar->start();

		foreach($products as $product) {
			$progressbar->advance();
			$currentQty = 0;

			try {
				$parentSku 		= preg_replace('/\D/', '', $product->getSku());
				$productMongo 	= $this->product->where('productId', (int) $parentSku)->first();
				$currentQty 	= $product->getQty();

				$item = $this->amazonListing
					->where('sku', $product->sku)
					->first();

				if (!$productMongo->isChannelTrue($channel)) {
					if ($currentQty == $item->qty) {
						$product->update(['ready_for_upload' => 0]);
						continue;
					}
					$currentQty = 0;
				}

				if (!$item || $currentQty == $item->qty) {
					$product->update(['ready_for_upload' => 0]);
					continue;
				}

				if (is_null($item->getProductType())) {
					continue;
				}

				$productType 	= $item->getProductType();
				$leadTime 		= $product->leadtime;

				if (!is_null($product->vendor) && isset($this->byPassLeadtime[$product->vendor])) {
					$leadTime 	= $this->byPassLeadtime[$product->vendor] ?? 3;
				}

				$value = [['fulfillment_channel_code' => 'DEFAULT', 'quantity' => $currentQty, 'lead_time_to_ship_max_days' => $leadTime]];

				if ($this->isLeadtimeExcluded($seller, $product->sku)) {
					unset($value[0]['lead_time_to_ship_max_days']);
				}

				$submit	= $this->listing->patchItem(
					$product->sku,
					'fulfillment_availability',
					$value,
					$productType
				);

				if (isset($submit['error']) || isset($submit['status']) && $submit['status'] == 'INVALID') {
					$product->update(['ready_for_upload' => 0]);
					continue;
				} else {
					if(!$product->update(["ready_for_upload" => 0])) {
						$this->error("[{$product->sku}] Failed to Update!");
					}
					$counter++;
				}
			}
			catch(Exception $e) {
				$this->info("Error Found:\t[$product->sku]\t'{$e->getMessage()}':\t At {$e->getFile()},\t line: {$e->getLine()}\n");
				continue;
			}

		}
		$progressbar->finish();
		$this->info("Total qty and leadtime processed: [$counter]");
		$fullTime = number_format(((Carbon::now()->timestamp - $begin->timestamp) / 60), 2, ".", ",");
		$this->info("Took: $fullTime minutes to process this marketplace.");
	}

	public function isLeadtimeExcluded($seller, $sku)
	{
		$leadtimeExcluded = $this->leadtimeExclusion
			->where('seller', $seller)
			->where('sku', $sku)
			->first();

		if ($leadtimeExcluded) {
			return true;
		}

		return false;
	}
}
