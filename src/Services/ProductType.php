<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Request;

class ProductType extends SpapiService
{
    public function getSuggestedProductType($marketplaceId, $keywords)
    {
        $request = new Request($this->seller->configurations(), $this->getProductTypeMethod());
        $response = $request->getAPIinstance()->searchDefinitionsProductTypes($marketplaceId, $keywords);
        try {
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]];
            return $response;
        }
    }

    public function fetchProductDefinition($productType, $marketplaceId, $sellerId)
    {
        $request = new Request($this->seller->configurations(), $this->getProductTypeMethod());
        $response = $request->getAPIinstance()->getDefinitionsProductType($productType, $marketplaceId);
        try {
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]];
            return $response;
        }
    }
}