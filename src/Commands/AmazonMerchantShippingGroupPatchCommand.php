<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use \Carbon\Carbon as Carbon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonMerchantShippingGroup as AmazonMerchantShippingGroup;
use Typhoeus\JleversSpapi\Models\MySql\MerchantShippingGroup as MerchantShippingGroup;

class AmazonMerchantShippingGroupPatchCommand extends Command
{
    use ConsoleOutput, TimeStamp;
	
    protected $signature = 'amz-spapi:upload:ship-template';
    protected $description = 'This command will patch or change the inventory and leadtime of our products';

    protected $listing;

    public function __construct(Listing $listing)
    {
        parent::__construct();
        $this->listing = $listing;
    }

	public function handle()
	{
		$this->listing->setSellerConfig(true);
		$shipGroupList	= new AmazonMerchantShippingGroup;
		$merchShipGroup	= new MerchantShippingGroup;
//		$hours = 12;//how long ago was the data updated
		$products = $shipGroupList->where('ready_for_upload', 1)
				->where('seller', $this->listing->app->getAppName())
//				->where('updated_at', '>=', Carbon::now()->subMinutes($hours*60)->toDateTimeString())//we need fresh data
				->get();//->take(1);
		$merchHash = $merchShipGroup->getTokenArray();
		if(empty($products)) $this->info("Warning! No update available for the last 12 hours!");
		else $this->info(count($products) . " items are being patched...");
		$counter = 0;
		$begin	= Carbon::now();
		$progressbar = $this->output->createProgressBar(count($products));
		$progressbar->start();

		foreach($products as $product) {
			$progressbar->advance();
			try {
				$submit = $this->listing->patchItem($product->sku, $attr = 'merchant_shipping_group', [["value" => $merchHash[$product->shipping_group]]]);
				if((!is_array($submit) && $submit->getStatus() == "ACCEPTED") || $submit == true) {
					if(!$product->update(["ready_for_upload" => 0])) {
						$this->error("[{$product->sku}] Failed to Update!");
					}
					$counter++;
				}
				else {
					continue;
				}
			}
			catch(Exception $e) {
				$this->info("Error Found:\t[$product->sku]\t'{$e->getMessage()}':\t At {$e->getFile()},\t line: {$e->getLine()}\n");
			}
			
		}
		$progressbar->finish();
		$this->info("Total prices processed: [$counter]");
		$fullTime = number_format(((Carbon::now()->timestamp - $begin->timestamp) / 60), 2, ".", ",");
		$this->info("Took: $fullTime minutes to process this marketplace.");
	}
}
