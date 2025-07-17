<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB;

use \Carbon\Carbon;
use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;
use Typhoeus\JleversSpapi\Models\MongoDB\ProcessCommand;

class SchedulerLog extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'scheduler_logs';
	protected $guarded = [];

	public $timestamps = true;
	public $incrementing = true;

    public function getProcessNameAttribute()
    {
    	$row = ProcessCommand::whereSignature($this->signature)->first();

    	if (is_null($row)) {
        	return 'N/A';
    	}

        return $row->name;
    }

    public function getProcessDurationAttribute()
    {
    	$start = Carbon::parse($this->process_start);
    	$end = Carbon::parse($this->process_end);

        return $end->diffForHumans($start);
    }
}
