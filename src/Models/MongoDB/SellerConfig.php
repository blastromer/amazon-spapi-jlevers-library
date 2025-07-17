<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Jenssegers\Mongodb\Eloquent\Model;

class SellerConfig extends Model
{
    /**
     * The database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'amazon_spapi_conn';

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection = 'sellers';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
}
