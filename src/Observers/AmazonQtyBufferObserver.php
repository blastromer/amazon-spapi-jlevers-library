<?php

namespace Typhoeus\JleversSpapi\Observers;

use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyBuffer;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyBufferLog;
use Exception;

class AmazonQtyBufferObserver
{
    /**
     * Handle the order item "created" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonQty  $row
     * @return void
     */
    public function created(AmazonQtyBuffer $row)
    {
        AmazonQtyBufferLog::create([
            'seller' => $row->seller,
            'sku' => $row->sku,
            'qty' => $row->qty
        ]);
    }

    /**
     * Handle the order item "updated" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonQty  $row
     * @return void
     */
    public function updated(AmazonQtyBuffer $row)
    {
        AmazonQtyBufferLog::create([
            'seller' => $row->seller,
            'sku' => $row->sku,
            'qty' => $row->qty
        ]);
    }

    /**
     * Handle the order item "deleted" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonQty  $row
     * @return void
     */
    public function deleted(AmazonQty $row)
    {
        //
    }

    /**
     * Handle the order item "restored" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonQty  $row
     * @return void
     */
    public function restored(AmazonQty $row)
    {
        //
    }

    /**
     * Handle the order item "force deleted" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonQty  $row
     * @return void
     */
    public function forceDeleted(AmazonQty $row)
    {
        //
    }
}
