<?php

namespace Typhoeus\JleversSpapi\Helpers;

use Config;
use Typhoeus\JleversSpapi\Models\MongoDB\SellerConfig;
use Typhoeus\JleversSpapi\Helpers\AppHelper;

class SellerConfigHelper extends AppHelper
{
    public $drivers     = ['mongodb', 'mysql'];
    public $config      = [];
    public $connection  = [];
    public $seller;

    /**
     * @param bool $hasModel
     */
    public function __construct(bool $hasModel = false)
    {
        $this->config = $hasModel ? $this->getModel() : [];
    }

    /**
     * @return array
     */
    public function configurations()
    {
        if (is_array($this->config)) {
            return $this->config ?? [];
        }

        return $this->config->toArray() ?? [];
    }

    /**
     * @param string $conn
     * @param string $database
     *
     * @return array
     */
    private function getModel($conn = 'amazon_spapi_conn', $database = 'amazon_sp')
    {
        $this->connection = $this->getDatabaseConfig()[$conn];

        if (!in_array($this->getDriver(), $this->drivers)) {
            return [];
        }

        if ($this->getDatabaseName() !== $database) {
            return [];
        }

        return $this->getConfigData() ?? [];
    }

    /**
     * @return array
     */
    private function getDatabaseConfig()
    {
        return config('database.connections');
    }

    /**
     * @return string
     */
    private function getDriver()
    {
        return $this->connection['driver'] ?? null;
    }

    /**
     * @return string
     */
    private function getDatabaseName()
    {
        return $this->connection['database'] ?? null;
    }

    /**
     * @return array
     */
    private function getConfigData()
    {
        return SellerConfig::where('website', $this->getAppName())
                            ->orWhere('seller_id', $this->getAppName())
                            ->first() ?? null;
    }

    /**
     * @return int
     */
    public function getBuffer()
    {
        return Config($this->getPackageName() . '::seller.' . $this->getAppName()) ?? 0;
    }
}