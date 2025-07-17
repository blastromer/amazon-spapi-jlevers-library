<?php

namespace Typhoeus\JleversSpapi\Helpers;

use Typhoeus\JleversSpapi\Helpers\AppHelper;
use Typhoeus\JleversSpapi\Models\MongoDB\Product;

class ProductHelper extends AppHelper
{
    public $vendorInitial = [
        'po' => 'plumbersstock',
        'kw' => 'kentucky'
    ];
    public $defaultVendor       = ['po', 'kw'];
    public $primaryVendors      = ['plumbersstock'];
    public $secondaryVendors    = ['kentucky'];
    public $buffer              = 0;

    public function getQtyAvailability($sellerSKU)
    {
        $parentSKU  = null;
        $vendorName = $this->vendorInitial['po'];
        if (preg_match('/[a-zA-Z]+/', $sellerSKU, $matches)) { // this will get the vendor initial like kw for kentucky branch 11 or po for branch 8
            if (!in_array($matches[0], $this->defaultVendor)) {
                $vendorName = $this->vendorInitial['po'];
            } else {
                $vendorName = $this->vendorInitial[$matches[0]];
            }
            $parentSKU  = (int) trim($sellerSKU);
        } else {
            $parentSKU  = (int) trim($sellerSKU);
        }

        $product    = Product::where('productId', (int) trim($parentSKU))->first();
        if (!$product) {
            return [false => ['message' => 'SKU was not found...']];
        }
        $fromDBQty  = (int) $product->getVendor($vendorName)['qty'] ?? 0;
        if ($fromDBQty <= $this->buffer && !in_array($vendorName, $this->secondaryVendors)) {
            $filtered = array_filter($product->getAvailability(), function ($vendor) { // Filter vendors with more than 2 qty
                return $vendor['qty'] > $this->buffer && $vendor['cost'] > 0;
            });
            if ($filtered == []) {
                $fromDBQty  = (int) $fromDBQty ?? 0;
            } else {
                $lowestCostVendor = array_reduce(array_keys($filtered), function ($carry, $vendor) use ($filtered) {
                    if ($carry === null || $filtered[$vendor]['cost'] < $filtered[$carry]['cost']) {
                        return $vendor;
                    }
                    return $carry;
                }, null);
                $fromDBQty  = (int) $product->getVendor($lowestCostVendor)['qty'] ?? 0;
            }
        }

        if ($fromDBQty <= 0) {
            $fromDBQty = 0;
        }

        return $fromDBQty;
    }
}