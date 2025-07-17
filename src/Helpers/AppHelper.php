<?php

namespace Typhoeus\JleversSpapi\Helpers;

use Config;
use Typhoeus\JleversSpapi\Helpers\AttributeHelper;
use Typhoeus\JleversSpapi\Helpers\SellerConfigHelper;
use Typhoeus\JleversSpapi\Helpers\DataHelper;
use Typhoeus\JleversSpapi\Helpers\FeedHelper;

class AppHelper
{
    /**
     * @var string
     */
    public static $appName;

    /**
     * @var AttributeHelper
     */
    public $attributeHelper;

    /**
     * @var SellerConfigHelper
     */
    public $sellerConfig;

    /**
     * @var mixed
     */
    public $config;

    /**
     * @var [type]
     */
    public $feedHelper;

    /**
     * AppHelper constructor.
     *
     * @param AttributeHelper $attributeHelper
     * @param SellerConfigHelper $sellerConfigHelper
     */
    public function __construct(
        AttributeHelper $attributeHelper,
        SellerConfigHelper $sellerConfigHelper,
        DataHelper $dataHelper
    ) {
        $this->attributeHelper  = $attributeHelper;
        $this->sellerConfig     = $sellerConfigHelper;
        $this->dataHelper       = $dataHelper;
    }

    /**
     * @var string $packageName
     */
    private static $packageName = 'jlevers-spapi';

    /**
     * Gets the package name
     * @return string
     */
    public function getPackageName()
    {
        return self::$packageName;
    }

    /**
     * Get application name.
     *
     * @return string|null
     */
    public function getAppName()
    {
        return config('app.name');
    }

    /**
     * Gets the working path of a certain package based on the object passed and used either from workbench or vendor
     * @param object $object
     * @return string
     */
    public function getWorkingPath($object)
    {
        $path                   = (new \ReflectionClass(get_class($object)))->getFileName();
        $path                   = str_replace('vendor', 'workbench', $path);
        $localWorkbenchFolder   = base_path() . DIRECTORY_SEPARATOR . 'workbench' . DIRECTORY_SEPARATOR . 'typhoeus'. DIRECTORY_SEPARATOR . self::getPackageName();
        $isWorkbench            = is_dir($localWorkbenchFolder) && file_exists($path);
        $path                   = base_path() . DIRECTORY_SEPARATOR . ($isWorkbench ? 'workbench' : 'vendor') . DIRECTORY_SEPARATOR . 'typhoeus'. DIRECTORY_SEPARATOR . self::getPackageName();

        return $path;
    }

    /**
     * Gets the template path if the file is being overridden in the template
     * @param string $dir
     * @param string $filename
     * @return string|null
     */
    public static function getTemplatePath($dir, $filename)
    {
        $path       = app_path() . DIRECTORY_SEPARATOR . 'Template' .  DIRECTORY_SEPARATOR . strtolower($dir) . DIRECTORY_SEPARATOR . self::getPackageName();
        $filepath   = $path . DIRECTORY_SEPARATOR . $filename;

        return (file_exists($filepath)) ? $path : null;
    }

    /**
     * @return array
     */
    public function getFeedType()
    {
        return FeedHelper::getType() ?? [];
    }

    /**
     * @param string $method
     *
     * @return string
     */
    public function getConfigMethods(string $method)
    {
        return Config($this->getPackageName() . '::methods.' . $method);
    }

    /**
     * @param string $method
     *
     * @return string
     */
    public function getConfigChannel(string $seller)
    {
        return Config($this->getPackageName() . '::channels.' . $seller) ?? null;
    }
}
