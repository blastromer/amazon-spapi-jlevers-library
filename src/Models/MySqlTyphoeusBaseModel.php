<?php

namespace Typhoeus\JleversSpapi\Models;

use Illuminate\Database\Eloquent\Model;

class MySqlTyphoeusBaseModel extends Model
{
    protected $connection = 'typhoeus_sql';
}