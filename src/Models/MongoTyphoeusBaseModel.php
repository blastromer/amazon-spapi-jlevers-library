<?php

namespace Typhoeus\JleversSpapi\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class MongoTyphoeusBaseModel extends Model
{
    protected $connection= 'mongo_typhoeus';
}
