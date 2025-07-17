<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Catalog;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQualifying;
use Illuminate\Support\Facades\Storage;

class SyncMongoToMysqlAsinCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi-test:sync:asin';
    protected $description = 'This command will sync or change the specific field';

    public function __construct(
        AmazonQualifying $amazonQualifying,
        Product $product,
        Catalog $catalog
    ) {
        parent::__construct();
        $this->catalog = $catalog;
        $this->amazonQualifying = $amazonQualifying;
        $this->product = $product;
    }

    public function handle()
    {
        $i      = 0;
        $err    = 0;
        $this->info('Syncing MongoDB Product ASIN to MySql Amazon Listing Table...');
        $this->catalog->setSellerConfig(true);
        $listings = $this->amazonQualifying->all();
        foreach ($listings as $list) {
            $productId  = $this->trimSku($list->getSku());
            $amazon     = $this->getAmazonData($productId);
            if ($amazon != []) {
                $mongoAsin = isset($amazon['asin']) ? $amazon['asin'] : $list->getAsin();
                $mysqlAsin = empty($list->getAsin()) ? null : $list->getAsin();
                if (!$this->isMatchedAsin($mongoAsin, $mysqlAsin)) {
                    $this->info("ASIN is not match, it will be snyced and update mysql ASIN...");
                    if (!$list->update(['asin' => $mongoAsin])) {
                        $this->error("Failed to sync ASIN...");
                    }
                } else {
                    $this->info("Matched or Null value both mongodb [{$mongoAsin}] and mysql [{$mysqlAsin}]..");
                }
            } else {
                $this->error("No amazon found...");
            }
            if (is_null($mysqlAsin)) {
                $retryCount = 0;
                $maxRetries = 5;
                do {
                    $suggestedCatalog = $this->catalog->getCatalogItemList($list->getUpc());
                    if (isset($suggestedCatalog['error'])) {
                        $errMsg = $suggestedCatalog['error']['message'];
                        if ($this->isExceeded($errMsg)) {
                            $retryCount++;
                            if ($retryCount >= $maxRetries) {
                                $this->error("Quota exceeded. Maximum retries reached for UPC: " . $list->getUpc());
                                $err++;
                                break;
                            }
                            sleep(1); // Increase if needed based on API restrictions
                            continue; // Retry the request
                        }
                        $this->error($errMsg);
                        $err++;
                        break; // Exit loop on other errors
                    }
                    $newASIN = $this->catalog->getAsin($suggestedCatalog);
                    if (is_null($newASIN)) {
                        $this->error("No suggested ASIN using this UPC/EAN [{$list->getUpc()}]");
                        break;
                    }
                    if (!$list->update(['asin' => $newASIN])) {
                        $this->error("Failed to update ASIN using this UPC/EAN [{$list->getUpc()}]");
                    }
                    $i++;
                    break; // Exit loop on success
                } while ($retryCount < $maxRetries);
            }
        }
        dump($i . ": error[{$err}]");
    }

    public function trimSku($sellerSku)
    {
        $productId = str_replace(['po','kw'], '', $sellerSku);
        return $productId;
    }

    public function getAmazonData($productId)
    {
        $product = $this->product->where('productId', (int) $productId)->first();
        return $product['amazon'] ?? [];
    }

    public function isMatchedAsin($fromMongo, $fromMysql)
    {
        if ($fromMongo != $fromMysql) {
            return false;
        }
        return true;
    }

    public function isExceeded($errMsg = "")
    {
        $jsonString = preg_replace('/^\[\d+\]\s*/', '', $errMsg);
        $data       = json_decode($jsonString, true);
        if ($data && isset($data['errors'][0]['code'])) {
            $errorCode      = $data['errors'][0]['code'];
            $errorMessage   = $data['errors'][0]['message'];
            if ($errorCode === 'QuotaExceeded') {
                return true;
            }
        }
        return false;
    }
}