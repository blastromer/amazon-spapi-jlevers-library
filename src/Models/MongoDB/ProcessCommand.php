<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLog;

class ProcessCommand extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'commands';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;

    public function getTotalRunAttribute()
    {
        $website = env('APP_NAME');
    	$count = SchedulerLog::whereWebsite($website)->whereSignature($this->signature)->where('process_start', 'LIKE', date('Y-m-d') . '%')->count();

        return $count;
    }
}
