<?php

namespace Typhoeus\JleversSpapi\Observers;

use Typhoeus\JleversSpapi\Models\MySql\AmazonQty;
use Typhoeus\JleversSpapi\Models\MySql\AmazonQtyLog;
use Exception;

class AmazonQtyObserver
{
    /**
     * Handle the order item "created" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonQty  $row
     * @return void
     */
    public function created(AmazonQty $row)
    {
        AmazonQtyLog::create([
            'seller' => $row->website,
            'sku' => $row->sku,
            'qty' => $row->qty,
            'qty_prev' => $row->qty_prev
        ]);
    }

    /**
     * Handle the order item "updated" event.
     *
     * @param  \Typhoeus\JleversSpapi\AmazonQty  $row
     * @return void
     */
    public function updated(AmazonQty $row)
    {
        if ($row->ready_for_upload == 1) {
            AmazonQtyLog::create([
                'seller' => $row->website,
                'sku' => $row->sku,
                'qty' => $row->qty,
                'qty_prev' => $row->qty_prev
            ]);
        }
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
