<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoTyphoeusBaseModel;

class TyphoeusProduct extends MongoTyphoeusBaseModel
{
	protected $database = 'typhoeus';
	protected $collection = 'products';
}
