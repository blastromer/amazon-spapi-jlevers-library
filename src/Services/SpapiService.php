<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Helpers\AppHelper;
use Typhoeus\JleversSpapi\Helpers\SellerConfigHelper;
use Typhoeus\JleversSpapi\Http\Request;

class SpapiService extends AppHelper
{
    protected   $spapi;
    protected   $appHelper;
    public      $seller;
    public      $sellerConfigHelper;

    public function __construct(
        AppHelper $appHelper
    ) {
        $this->app = $appHelper;
    }

    /**
     * @param bool $hasModel
     *
     * @return [type]
     */
    public function setSellerConfig(bool $hasModel = false)
    {
        $this->seller   = new SellerConfigHelper($hasModel);
        $configuration  = $this->seller->configurations();
        if (!$configuration) {
            return response()->json(['error' => 'Credentials not found'], 404);
        }

        return true;
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getListingMethod()
    {
        return $this->app->getConfigMethods('listing');
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getPricingMethod()
    {
        return $this->app->getConfigMethods('pricing');
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getFeedMethod()
    {
        return $this->app->getConfigMethods('feed');
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getCatalogMethod()
    {
        return $this->app->getConfigMethods('catalog');
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getListingRestrictionMethod()
    {
        return $this->app->getConfigMethods('listing_restriction');
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getReportMethod()
    {
        return $this->app->getConfigMethods('report');
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getProductTypeMethod()
    {
        return $this->app->getConfigMethods('product_type');
    }

    /**
     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper
     */
    public function getSellers()
    {
        return $this->app->getConfigMethods('seller');
    }
    /**

     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper

     */

    public function getOrderMethod()
    {
        return $this->app->getConfigMethods('order');
    }

    /**

     * @return \Typhoeus\JleversSpapi\Helpers\AppHelper

     */

    public function getFinanceMethod()
    {
        return $this->app->getConfigMethods('finance');
    }
}
