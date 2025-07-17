<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use \Carbon\Carbon as Carbon;
use Typhoeus\JleversSpapi\Models\MySql\AmazonDescription as AmazonDescription;
use Typhoeus\JleversSpapi\Models\MySql\AmazonDescriptionLogs as AmazonDescriptionLogs;

class AmazonDescriptionPatchCommand extends Command
{
    use ConsoleOutput, TimeStamp;
	
    protected $signature = 'amz-spapi:upload:desc';
    protected $description = 'This command will update the missing descriptions of our products';

    protected $listing;

    public function __construct(Listing $listing)
    {
        parent::__construct();
        $this->listing = $listing;
    }

	public function handle()
	{
		$this->listing->setSellerConfig(true);
		$amzDescriptions	= new AmazonDescription;
		$products = $amzDescriptions->where('ready_for_upload', '=', 1)->get(["sku", "description", "ready_for_upload"]);
		$count	= 0;
		$begin	= Carbon::now();
		$progressbar = $this->output->createProgressBar(count($products));
		$progressbar->start();
		foreach($products as $product) {
			$progressbar->advance();
			try {
				$value = [
					[
						"value" => $product->description,
						"language_tag"  => "en_US"
					]
				];

				$submit	= $this->listing->patchItem($product->sku, $attr = 'product_description', $value);

				if(is_array($submit) || $submit->getStatus() != "ACCEPTED") {
					continue;
				}
				else {
					$amzDescLogs = new AmazonDescriptionLogs;
					$amzDescLogs->sku = $product->sku;
					$amzDescLogs->desc = $product->description;
					$amzDescLogs->save();
					$amzDescriptions->where("sku", $product->sku)->update(["ready_for_upload" => 0]);
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