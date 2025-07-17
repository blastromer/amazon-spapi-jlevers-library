<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlProductBaseModel;

class PriceConfig extends MySqlProductBaseModel
{
    protected $database = 'products';
    protected $table = 'amazon_priceConfig';
    public $timestamps = false;

    // Merchant
    public function getMerchant()
    {
        return $this->merchant;
    }

    // LT2 Pricing
    public function getMinGP_lt2()
    {
        return $this->minGP_lt2;
    }

    public function getMaxGP_lt2()
    {
        return $this->maxGP_lt2;
    }

    public function getBeatBy_lt2()
    {
        return $this->beatBy_lt2;
    }

    // LT5 Pricing
    public function getMinGP_lt5()
    {
        return $this->minGP_lt5;
    }

    public function getMaxGP_lt5()
    {
        return $this->maxGP_lt5;
    }

    public function getBeatBy_lt5()
    {
        return $this->beatBy_lt5;
    }

    // LT20 Pricing
    public function getMinGP_lt20()
    {
        return $this->minGP_lt20;
    }

    public function getMaxGP_lt20()
    {
        return $this->maxGP_lt20;
    }

    public function getBeatBy_lt20()
    {
        return $this->beatBy_lt20;
    }

    // LT50 Pricing
    public function getMinGP_lt50()
    {
        return $this->minGP_lt50;
    }

    public function getMaxGP_lt50()
    {
        return $this->maxGP_lt50;
    }

    public function getBeatBy_lt50()
    {
        return $this->beatBy_lt50;
    }

    // LT100 Pricing
    public function getMinGP_lt100()
    {
        return $this->minGP_lt100;
    }

    public function getMaxGP_lt100()
    {
        return $this->maxGP_lt100;
    }

    public function getBeatBy_lt100()
    {
        return $this->beatBy_lt100;
    }

    // LT200 Pricing
    public function getMinGP_lt200()
    {
        return $this->minGP_lt200;
    }

    public function getMaxGP_lt200()
    {
        return $this->maxGP_lt200;
    }

    public function getBeatBy_lt200()
    {
        return $this->beatBy_lt200;
    }

    // LT500 Pricing
    public function getMinGP_lt500()
    {
        return $this->minGP_lt500;
    }

    public function getMaxGP_lt500()
    {
        return $this->maxGP_lt500;
    }

    public function getBeatBy_lt500()
    {
        return $this->beatBy_lt500;
    }

    // GT500 Pricing
    public function getMinGP_gt500()
    {
        return $this->minGP_gt500;
    }

    public function getMaxGP_gt500()
    {
        return $this->maxGP_gt500;
    }

    public function getBeatBy_gt500()
    {
        return $this->beatBy_gt500;
    }

    // Packaging & Foam
    public function getPackaging()
    {
        return $this->packaging;
    }

    public function getFoam()
    {
        return $this->foam;
    }
}
