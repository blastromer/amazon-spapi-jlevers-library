<?php

namespace Typhoeus\JleversSpapi\Providers;

use Illuminate\Support\ServiceProvider;
use Jlevers\SellingPartnerApi\SellingPartnerApi;
use Typhoeus\JleversSpapi\Commands\TestCommand;
use Typhoeus\JleversSpapi\Commands\GetPriceCommand;
use Typhoeus\JleversSpapi\Helpers\AppHelper;
use Typhoeus\JleversSpapi\Helpers\AttributeHelper;
use Typhoeus\JleversSpapi\Helpers\SellerConfigHelper;
use Typhoeus\JleversSpapi\Helpers\DataHelper;
use Typhoeus\JleversSpapi\Helpers\FeedHelper;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQty;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyBuffer;
use Typhoeus\JleversSpapi\Observers\AmazonPriceObserver;
use Typhoeus\JleversSpapi\Observers\AmazonQtyBufferObserver;
use Typhoeus\JleversSpapi\Observers\AmazonQtyObserver;

class JleversSpapiServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AttributeHelper::class, function () {
            return new AttributeHelper();
        });

        $this->app->singleton(SellerConfigHelper::class, function () {
            return new SellerConfigHelper();
        });

        $this->app->singleton(DataHelper::class, function () {
            return new DataHelper();
        });

        $this->app->singleton(AppHelper::class, function ($app) {
            return new AppHelper(
                $app->make(AttributeHelper::class),
                $app->make(SellerConfigHelper::class),
                $app->make(DataHelper::class),
                $app->make(FeedHelper::class),
            );
        });

        $this->loadConfigs();
        $this->initializeObservers();
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->initializeAppHelper();
            $path = AppHelper::getWorkingPath($this) . DIRECTORY_SEPARATOR . 'src/Commands';
            if (file_exists($path)) {
                $commands = [];
                foreach (scandir($path) as $command) {
                    if (pathinfo($command, PATHINFO_EXTENSION) == 'php') {
                        $command    = rtrim($command, '.php');
                        $commands[] = "\\Typhoeus\\JleversSpapi\\Commands\\{$command}";
                    }
                }
                if ($commands) {
                    $this->commands($commands);
                }
            }
		}
        $this->loadViewsFrom(__DIR__ . '/../Views', 'amz-spapi');
    }

    /**
     * Loads package configuration
     *
     * @return void
     */
    private function loadConfigs()
    {
        $packageName = AppHelper::getPackageName();
        $workingPath = AppHelper::getWorkingPath($this) . DIRECTORY_SEPARATOR . 'src/Config';
        foreach (scandir($workingPath) as $config) {
            if (pathinfo($config, PATHINFO_EXTENSION) == 'php') {
                $name       = pathinfo($config, PATHINFO_FILENAME);
                $configName = "{$packageName}::{$name}";
                // Sets the template path if overridden in the template, and sets the working path if not overridden
                $path       = AppHelper::getTemplatePath('config', $config) ?? $workingPath;
                $this->mergeConfigFrom($path . DIRECTORY_SEPARATOR . $config, $configName);
                // Insert seo configs to existing configs if available
                if ($configValue = config($name)) {
                    config([$name => array_replace_recursive($configValue, config($configName))]);
                }
            }
        }
    }

     /**
     * Initialize AppHelper.
     *
     * @return void
     */
    protected function initializeAppHelper()
    {
        $appHelper = $this->app->make(AppHelper::class);
    }

    /**
     * Initialize Observers.
     *
     * @return void
     */

    public function initializeObservers()
    {
        AmazonPrice::observe(AmazonPriceObserver::class);
        AmazonQtyBuffer::observe(AmazonQtyBufferObserver::class);
        AmazonQty::observe(AmazonQtyObserver::class);
    }
}
