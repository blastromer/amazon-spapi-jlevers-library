<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Feed\Pricing;
use Typhoeus\JleversSpapi\Http\Feed\Inventory;
use Typhoeus\JleversSpapi\Http\Feed\Listing;

class Feed extends SpapiService
{
    public function createFeed()
    {
        $updateMethod = new Pricing($this->seller->configurations() ?? [], 'FeedsV20210630Api'); // this is working for update price
        // $updateMethod = new Inventory($this->seller->configurations(), $this->getFeedMethod());
        $updatedPrice = $updateMethod->updatePrice(); // this is working
        // $updateQTY = $updateMethod->updateInventory();
        // $updateMethod->checkFeedID(); // This check the Submitted Feed using the ID
        // $updateMethod->cancelFeedID(); // This will attemp the Submiited Feed using the ID

        return $updateQTY;
    }

    public function uploadFeed($fileName)
    {
        $listings = new Listing($this->seller->configurations() ?? [], 'FeedsV20210630Api'); // this is working for update price
        // $updateMethod = new Inventory($this->seller->configurations(), $this->getFeedMethod());
        $response = $listings->uploadItems($fileName); // this is working
        // $updateQTY = $updateMethod->updateInventory();
        // $updateMethod->checkFeedID(); // This check the Submitted Feed using the ID
        // $updateMethod->cancelFeedID(); // This will attemp the Submiited Feed using the ID

        return $response;
    }

    public function cancelFeed()
    {

    }

    public function checkFeedID($feedId)
    {
        $feed = new Listing($this->seller->configurations() ?? [], 'FeedsV20210630Api'); // this is working for update price
        // $updateMethod = new Inventory($this->seller->configurations(), $this->getFeedMethod());
        $response = $feed->checkFeedID($feedId); // this is working
        // $updateQTY = $updateMethod->updateInventory();
        // $updateMethod->checkFeedID(); // This check the Submitted Feed using the ID
        // $updateMethod->cancelFeedID(); // This will attemp the Submiited Feed using the ID

        return $response;

        // $feedId = '1317921020116'; // Ensure this is a valid Amazon feed ID

        // try {
        //     $updateMethod = new Pricing($this->seller->configurations() ?? [], 'FeedsV20210630Api');

        //     // Step 1: Get feed details
        //     $feedResponse   = $this->apiInstance->getFeed($feedId);

        //     // Step 2: Check the feed processing status
        //     $feedStatus     = $feedResponse->getProcessingStatus();
        //     echo "Feed Status: " . $feedStatus . "\n";

        //     if ($feedStatus === 'DONE') {
        //         echo "Feed completed successfully.\n";

        //         // Step 3: Get the result feed document ID
        //         $feedDocumentId = $feedResponse->getResultFeedDocumentId();

        //         if ($feedDocumentId) {
        //             echo "Fetching processing report...\n";

        //             // Step 4: Get the feed document details
        //             $feedDocumentResponse = $this->apiInstance->getFeedDocument($feedDocumentId);
        //             $documentUrl = $feedDocumentResponse->getUrl();

        //             // Step 5: Download the feed processing report
        //             $reportContent = file_get_contents($documentUrl);
        //             $decodedReport = json_decode($reportContent, true);

        //             // Step 6: Check the report for errors
        //             if (isset($decodedReport['errors']) && count($decodedReport['errors']) > 0) {
        //                 echo "Feed processing errors found:\n";
        //                 print_r($decodedReport['errors']);
        //             } else {
        //                 echo "Feed processed with no errors.\n";
        //             }
        //         } else {
        //             echo "No processing report available.\n";
        //         }
        //     } else {
        //         echo "Feed is still being processed.\n";
        //     }
        // } catch (\SellingPartnerApi\ApiException $e) {
        //     echo "API Error: " . $e->getMessage();
        // } catch (\Exception $e) {
        //     echo "Error: " . $e->getMessage();
        // }
    }
}