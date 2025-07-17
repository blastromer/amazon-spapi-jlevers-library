<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Request;

class Order extends SpapiService
{
    public function getOrders($marketplace_ids, $created_after = null, $created_before = null, $last_updated_after = null, $last_updated_before = null, $order_statuses = null, $fulfillment_channels = null, $payment_methods = null, $buyer_email = null, $seller_order_id = null, $max_results_per_page = null, $easy_ship_shipment_statuses = null, $electronic_invoice_statuses = null, $next_token = null, $amazon_order_ids = null, $actual_fulfillment_supply_source_id = null, $is_ispu = null, $store_chain_store_id = null, $data_elements = null)
    {
        $request = new Request($this->seller->configurations(), $this->getOrderMethod());
        $orders = $request->getAPIinstance()
->getOrders($marketplace_ids, $created_after, $created_before, $last_updated_after, $last_updated_before, $order_statuses, $fulfillment_channels, $payment_methods, $buyer_email, $seller_order_id, $max_results_per_page, $easy_ship_shipment_statuses, $electronic_invoice_statuses, $next_token, $amazon_order_ids, $actual_fulfillment_supply_source_id, $is_ispu, $store_chain_store_id, $data_elements)->getPayload();

        return $orders ?? [];
    }

    public function getOrderItems($order_id, $next_token = null, $data_elements = null)
    {
        $request = new Request($this->seller->configurations(), $this->getOrderMethod());
        $items = $request->getAPIinstance()->getOrderItems($order_id, $next_token, $data_elements)->getPayload();

        return $items ?? [];
    }

    public function getOrderAddress($order_id)
    {
        $request = new Request($this->seller->configurations(), $this->getOrderMethod());
        $items = $request->getAPIinstance()->getOrderAddress($order_id)->getPayload();

        return $items ?? [];
    }

    public function getOrderBuyerInfo($order_id)
    {
        $request = new Request($this->seller->configurations(), $this->getOrderMethod());
        $items = $request->getAPIinstance()->getOrderBuyerInfo($order_id)->getPayload();

        return $items ?? [];
    }

    public function confirmShipment($order_id, $payload)
    {
        $request = new Request($this->seller->configurations(), $this->getOrderMethod());
        $response = $request->getAPIinstance()->confirmShipment($order_id, $payload);

        return $response;
    }
}