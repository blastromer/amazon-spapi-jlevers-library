<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoTyphoeusBaseModel;
use Exception;

class Category extends MongoTyphoeusBaseModel
{
    /**
     * The database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mongodb_typhoeus_conn';

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection = 'categories';

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