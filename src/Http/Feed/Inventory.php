<?php

namespace Typhoeus\JleversSpapi\Http\Feed;

use SellingPartnerApi\Document;
use SellingPartnerApi\Configuration;
use SellingPartnerApi\Model\FeedsV20210630\CreateFeedSpecification;
use SellingPartnerApi\Model\FeedsV20210630\CreateFeedDocumentSpecification;
use Illuminate\Support\Facades\Storage;
use Typhoeus\JleversSpapi\Http\Request;

class Inventory extends Request
{
    /**
     * @return [type]
     */
    public function updateInventory()
    {
        $feedType   = $this->getFeedType();
        $fileExt    = '.json';
        if ($feedType['name'] == 'POST_PRODUCT_PRICING_DATA') {
            $fileExt = '.xml';
        }

        // Create feed document
        $createFeedDocSpec  = new CreateFeedDocumentSpecification(['content_type' => $feedType['contentType']]);
        $feedDocumentInfo   = $this->apiInstance->createFeedDocument($createFeedDocSpec);
        $feedDocumentId     = $feedDocumentInfo->getFeedDocumentId();
        $feedFile           = 'product_update' . $fileExt;
        dump('Feed File Available: ' . $feedFile);

        // Upload feed contents to document
        if (Storage::exists($feedFile)) {
            // File exists, proceed to get contents
            $feedContents   = Storage::get($feedFile);
            // Process the file contents here
        } else {
            // File does not exist, handle the error
            dd('File does not exist: ' . $feedFile);
        }

        // The Document constructor accepts a custom \GuzzleHttp\Client object as an optional 3rd parameter. If that
        // parameter is passed, your custom Guzzle client will be used when uploading the feed document contents to Amazon.
        $docToUpload = new Document($feedDocumentInfo, $feedType);
        $docToUpload->upload($feedContents);
        sleep(10);

        // This is not present in the example
        $body               = new CreateFeedSpecification();
        $body->setMarketplaceIds([$this->configuration['marketplace_id']]);
        $body->setInputFeedDocumentId($feedDocumentId);
        $body->setFeedType($feedType['name']);
        $result             = $this->apiInstance->createFeed($body);
        sleep(10);

        $feed_id            = $result->getFeedId();

        return 'Feed Summission ID: '.$feed_id;
    }

    public function checkFeedID()
    {
        $feedId = '1317915020116'; // Ensure this is a valid Amazon feed ID

        try {
            // Step 1: Get feed details
            $feedResponse   = $this->apiInstance->getFeed($feedId);

            // Step 2: Check the feed processing status
            $feedStatus     = $feedResponse->getProcessingStatus();
            echo "Feed Status: " . $feedStatus . "\n";

            if ($feedStatus === 'DONE') {
                echo "Feed completed successfully.\n";

                // Step 3: Get the result feed document ID
                $feedDocumentId = $feedResponse->getResultFeedDocumentId();

                if ($feedDocumentId) {
                    echo "Fetching processing report...\n";

                    // Step 4: Get the feed document details
                    $feedDocumentResponse = $this->apiInstance->getFeedDocument($feedDocumentId);
                    $documentUrl = $feedDocumentResponse->getUrl();

                    // Step 5: Download the feed processing report
                    $reportContent = file_get_contents($documentUrl);
                    $decodedReport = json_decode($reportContent, true);

                    // Step 6: Check the report for errors
                    if (isset($decodedReport['errors']) && count($decodedReport['errors']) > 0) {
                        echo "Feed processing errors found:\n";
                        print_r($decodedReport['errors']);
                    } else {
                        echo "Feed processed with no errors.\n";
                    }
                } else {
                    echo "No processing report available.\n";
                }
            } else {
                echo "Feed is still being processed.\n";
            }
        } catch (\SellingPartnerApi\ApiException $e) {
            echo "API Error: " . $e->getMessage();
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}