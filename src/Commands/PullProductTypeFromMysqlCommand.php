<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\AmazonAsinProductType;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;
use Carbon\Carbon;

class PullProductTypeFromMysqlCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:product-type:pull-mysql';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(
        AmazonAsinProductType $amazonAsinProductType,
        AmazonQualifying $amazonQualifying,
        Catalog $catalog
    ) {
        parent::__construct();
        $this->amazonAsinProductType = $amazonAsinProductType;
        $this->amazonQualifying = $amazonQualifying;
        $this->catalog = $catalog;
    }

    public function handle()
    {
        $this->catalog->setSellerConfig(true);
        $this->info("Getting Product Type using ASIN from Mysql Database...");
        $producQfy = $this->amazonQualifying
            ->selectRaw('
                sku,
                MIN(id) as id,
                MAX(asin) as asin,
                MAX(title) as title,
                MAX(model_number) as model_number,
                MAX(part_number) as part_number,
                MAX(brand) as brand,
                MAX(product_group) as product_group,
                MAX(product_type) as product_type,
                MAX(publisher) as publisher,
                MAX(studio) as studio,
                MAX(package_qty) as package_qty,
                MAX(upc) as upc,
                MAX(is_uploaded) as is_uploaded,
                MAX(is_skipped) as is_skipped
            ')
            ->groupBy('sku')
            ->orderBy('id')
            ->get();
        $bar        = new ProgressBar(new Console(), $producQfy->count());
        $bar->setFormat('Product Type Screening %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();
        foreach ($producQfy as $item) {
            $bar->advance();
            $asin           = $item->getAsin();
            $maxAttempts    = 2; // Set a maximum retry limit
            $attempt        = 0;
            $success        = false;
            $productType    = $this->amazonAsinProductType->where('asin', $asin);
            $ifNull         = $productType->first();

            // if ($ifNull && is_null($ifNull->product_type)) {
            //     $item->update(['is_skipped' => 1]);
            //     dump($asin);
            // }
            if ($item->is_skipped) {
                continue;
            }

            if ($productType->exists() && !is_null($ifNull->product_type)) {
                continue;
            }

            do {
                $attempt++;
                $response = $this->catalog->getProductTypeByAsin($asin);

                if (is_null($response)) {
                    $item->update(['is_skipped' => 1]);
                    continue;
                }
                if (isset($response['error'])) {
                    // dump($asin);
                    sleep(3);
                } else {
                    // If there's no error, we successfully got the response
                    // dump($asin . "=". $response);
                    // $this->amazonAsinProductType->updateOrCreate(
                    //     ['asin' => $asin], // Find by ASIN
                    //     ['product_type' => $response] // Update or insert with this value
                    // );
                    $success = true;
                }
            } while (!$success && $attempt < $maxAttempts);

            // If max attempts reached and still failing, log it
            if (!$success) {
                $item->update(['is_skipped' => 1]);
                // echo "Failed to get product type for ASIN: $asin after $maxAttempts attempts.\n";
            }
        }
        $bar->finish();
    }
}