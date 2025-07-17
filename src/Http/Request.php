<?php

namespace Typhoeus\JleversSpapi\Http;

use SellingPartnerApi\Api\ListingsV20210801Api;
use SellingPartnerApi\Configuration;
use SellingPartnerApi\Endpoint;
use Typhoeus\JleversSpapi\Helpers\AppHelper;

class Request extends AppHelper
{
    protected $apiInstance;
    protected $configuration;
    protected $apiClassName;

    protected $baseAPIDir = '\SellingPartnerApi\\Api\\';

    public function __construct(
        $config     = [],
        $className  = null
    ) {
        $this->apiInstance      = $this->createDefaultHttpClient($config, $className);
        $this->configuration    = $config;
    }

    protected function createDefaultHttpClient($config, $className)
    {

        try {
            $this->apiClassName = $className;

            $configuration = new Configuration([
                'lwaClientId'        => $config['app_client_id'] ?? '',
                'lwaClientSecret'    => $config['app_client_secret'] ?? '',
                'lwaRefreshToken'    => $config['refresh_token'] ?? '',
                'awsAccessKeyId'     => $config['access_key'] ?? '',
                'awsSecretAccessKey' => $config['secret_key'] ?? '',
                'roleArn'            => $config['role_arn'] ?? '',
                'endpoint'           => Endpoint::NA
            ]);

            $className = $this->baseAPIDir . $className;

            return new $className($configuration);
        } catch (\Exception $e) {
            dump($e);
            return ['error' => $e->getMessage()];
        }
    }

    public function getItem(string $sellerSku)
    {
        try {
            $products   = [];
            $result     = $this->apiInstance->getListingsItem(
                $this->configuration['merchant_id'],
                $sellerSku,
                $this->configuration['marketplace_id'],
            );
            $items      = $result->getSummaries();  // This will return an array of summaries

            return $items;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getPrices(array $sellerSku)
    {
        try {
            $products   = [];
            $result     = $this->apiInstance->getPricing(
                $this->configuration['marketplace_id'],
                'Sku',
                null,
                $sellerSku
            );
            $items      = $result->getPayload();  // This will return an array of summaries

            return $items;
        } catch (\Exception $e) {
            dump($e);
            return ['error' => $e->getMessage()];
        }
    }

    public function getAPIinstance()
    {
        return $this->apiInstance;
    }

    public function getItemDetails(string $sellerSku, $attr = 'product_type')
    {
        try {
            $products   = [];
            $result     = $this->apiInstance->getListingsItem(
                isset($this->configuration['merchant_id']) ? $this->configuration['merchant_id'] : $this->configuration['amazon_merchant_id'],
                $sellerSku,
                $this->configuration['marketplace_id']
            );

            $items      = (method_exists($result, 'getPayload')) ? $result->getPayload() : $result->getSummaries()[0];  // This should return detailed info
            if (isset($items[$attr])) {
                $productType = $items[$attr];  // Access product_type here
                return $productType;
            } else {
                return 'Product type not available';
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function patchItem()
    {

    }
}
