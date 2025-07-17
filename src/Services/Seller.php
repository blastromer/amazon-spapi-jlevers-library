<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Request;

class Seller extends SpapiService
{
    public function getShippingGroup()
    {
        $request = new Request($this->seller->configurations(), $this->getSellers());
        $response = $request->getAPIinstance()->getMarketplaceParticipations();

        return $response;
    }
}