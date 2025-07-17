<?php

namespace Typhoeus\JleversSpapi\Helpers;

class AttributeHelper
{
    public function getDefaultName()
    {
        return config('app.name');
    }
}