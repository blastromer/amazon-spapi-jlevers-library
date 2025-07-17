<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Services\Seller;
use Typhoeus\JleversSpapi\Services\Listing;

class DecodeMerchantShippingGroupCommand extends Command
{
    use ConsoleOutput;

    protected $signature    = 'amz-spapi:decode';
    protected $description  = 'Test command for SP-API';

    protected $spapiService;

    public function __construct(
        Listing $listing
    ) {
        parent::__construct();
        $this->listing = $listing;
    }

    public function handle()
    {
        $sku = '361562';
        $includedData   = 'issues,summaries,attributes';
        $this->listing->setSellerConfig(true);
        $sellerId       = $this->listing->seller->config['amazon_merchant_id'];
        $marketplaceId  = $this->listing->seller->config['marketplace_id'];
        $responseIssue  = $this->listing->getItem($sellerId, $sku, $marketplaceId, $issueLocale = 'en_US', $includedData);
        dump($responseIssue);
    }
}
