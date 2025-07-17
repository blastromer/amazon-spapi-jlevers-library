<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use \Carbon\Carbon as Carbon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying as AmazonQualifying;
use Typhoeus\JleversSpapi\Models\MySql\AmazonUomLog as AmazonUomLog;

class AmazonUomPatchCommand extends Command
{
    use ConsoleOutput, TimeStamp;
	
    protected $signature = 'amz-spapi:upload:uom';
    protected $description = 'This command will patch or change the price range of our products';

    protected $listing;

    public function __construct(Listing $listing)
    {
        parent::__construct();
        $this->listing = $listing;
    }

	public function handle()
	{
		$this->listing->setSellerConfig(true);
		$amzQualified	= new AmazonQualifying;
		$amazonUomLog	= new AmazonUomLog;
		$products = $amzQualified->where('package_qty', '>', 1)
				->where('is_uploaded', '=', 1)
				->where('seller', $this->listing->app->getAppName())
				->get();//->take(2);
		$count	= 0;
		$begin	= Carbon::now();
		$uom = 1;
		$progressbar = $this->output->createProgressBar(count($products));
		$progressbar->start();
		foreach($products as $product) {
			$progressbar->advance();
			try {
				$noiSubmit	= $this->listing->patchItem($product->sku, $attr = 'number_of_items', [["value" => $uom]]);

				$value = [
					[
						"value" => $uom,
						"type"  => [
							"language_tag"  => "en_US",
							"value"         => "Count"
						]
					]
				];

				$ucSubmit = $this->listing->patchItem($product->sku, $attr = 'unit_count', $value);

				if(is_array($ucSubmit) || $ucSubmit->getStatus() != "ACCEPTED") {
					continue;
				}
				else {
					$unitCount = $uom;
				}
				if(is_array($noiSubmit) || $noiSubmit->getStatus() != "ACCEPTED") {
					continue;
				}
				else {
					$numOfItems = $uom;
				}

				$logs = $amazonUomLog->where('sku', $product->sku)
							->where('seller', $this->listing->app->getAppName())
							->first();

				if(empty($logs)) {
					$amazonUomLog->sku				= $product->sku;
					$amazonUomLog->seller			= $this->listing->app->getAppName();
					$amazonUomLog->unit_count		= $unitCount;
					$amazonUomLog->number_of_items	= $numOfItems;
					$amazonUomLog->previous_uom		= $product->package_qty;
					$saved							= $amazonUomLog->save();
				}
				elseif($logs->unit_count != $uom) {
					$logs->update(["unit_count" => $unitCount, "number_of_items" => $numOfItems, "previous_uom" => $logs->unit_count]);
				}
			}
			catch(Exception $e) {
				$this->info("Error Found:\t'{$e->getMessage()}':\t At {$e->getFile()},\t line: {$e->getLine()}\n");
				continue;
			}
		}
		$progressbar->finish();
		$fullTime = number_format(((Carbon::now()->timestamp - $begin->timestamp) / 60), 2, ".", ",");
		$this->info("Took: $fullTime minutes to process this marketplace.");
	}
}