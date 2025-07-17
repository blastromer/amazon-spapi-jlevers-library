<?php

namespace Typhoeus\JleversSpapi\Helpers;

use SellingPartnerApi\FeedType;

class FeedHelper extends AppHelper
{
    /**
     * @return array
     */
    public function getType(string $type = 'json')
    {
        return Config(self::getPackageName() . '::feed.' . $type) ?? [];
    }
}