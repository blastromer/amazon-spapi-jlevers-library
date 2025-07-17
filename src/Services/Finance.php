<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Request;

class Finance extends SpapiService
{
    public function listFinancialEventsByOrderId($order_id, $max_results_per_page = 100, $next_token = null)
    {
        $request = new Request($this->seller->configurations(), $this->getFinanceMethod());
        $response = $request->getAPIinstance()->listFinancialEventsByOrderId($order_id, $max_results_per_page, $next_token)->getPayload();

        return $response;
    }
}