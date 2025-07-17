<?php

namespace Typhoeus\JleversSpapi\Observers;

use Typhoeus\JleversSpapi\Models\MySql\AmazonPrice;
use Typhoeus\JleversSpapi\Models\MySql\AmazonPriceLog;
use Exception;

class AmazonPriceObserver
{
    /**
     * Handle the order item "created" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonPrice  $row
     * @return void
     */
    public function created(AmazonPrice $row)
    {
        AmazonPriceLog::create([
            'seller'        => $row->seller,
            'sku'           => $row->sku,
            'listing_price' => $row->listing_price,
            'own_price'     => $row->own_price,
            'min_price'     => $row->min_price,
            'max_price'     => $row->max_price,
            'map_price'     => $row->map_price,
        ]);
    }

    /**
     * Handle the order item "updated" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonPrice  $row
     * @return void
     */
    public function updated(AmazonPrice $row)
    {
        if ($row->ready_for_upload == 1) {
            AmazonPriceLog::create([
                'seller'        => $row->seller,
                'sku'           => $row->sku,
                'listing_price' => $row->listing_price,
                'own_price'     => $row->own_price,
                'min_price'     => $row->min_price,
                'max_price'     => $row->max_price,
                'map_price'     => $row->map_price,
            ]);
        }
    }

    /**
     * Handle the order item "deleted" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonPrice  $row
     * @return void
     */
    public function deleted(AmazonPrice $row)
    {
        //
    }

    /**
     * Handle the order item "restored" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonPrice  $row
     * @return void
     */
    public function restored(AmazonPrice $row)
    {
        //
    }

    /**
     * Handle the order item "force deleted" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonPrice  $row
     * @return void
     */
    public function forceDeleted(AmazonPrice $row)
    {
        //
    }
}
