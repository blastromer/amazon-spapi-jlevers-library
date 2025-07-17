<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Request;

class Pricing extends SpapiService
{
    public function getItemsPrice(array $skus)
    {
        $request        = new Request($this->seller->configurations(), $this->getPricingMethod());
        $productList    = $request->getPrices($skus);

        return $productList ?? [];
    }

    public function getItemPrice(string $sku)
    {
        $request        = new Request($this->seller->configurations(), $this->getPricingMethod());
        $productList    = $request->getPrice($sku);

        return $this->app->dataHelper->aggregateData($productList);
    }

    public function getItemOffers($sku)
    {
        $request        = new Request($this->seller->configurations(), $this->getPricingMethod());
        // $listingOffers  = $request->getAPIinstance()->getItemOffers('ATVPDKIKX0DER', 'New', '921626po');
        // $listingOffers  = $request->getAPIinstance()->getItemOffers('ATVPDKIKX0DER', 'New', 'B0C7BJPBBM');
        // $listingOffers  = $request->getAPIinstance()->getPricing('ATVPDKIKX0DER', 'Sku', null, ['921626po']);
        // $listingOffers  = $request->getAPIinstance()->getCompetitivePricing('ATVPDKIKX0DER', 'Sku', null, ['921626po']);
        $listingOffers  = $request->getAPIinstance()->getListingOffers('ATVPDKIKX0DER', 'New', $sku);

        return $listingOffers;
    }

    public function getCompetitivePricing($marketplace_id = 'ATVPDKIKX0DER', $item_type, $asins = null, $skus = null, $customer_type = null)
    {
        $request = new Request($this->seller->configurations(), $this->getPricingMethod());
        $rows  = $request->getAPIinstance()->getCompetitivePricing($marketplace_id, $item_type, $asins, $skus, $customer_type);
        return $rows->getPayload() ?? [];
    }
}