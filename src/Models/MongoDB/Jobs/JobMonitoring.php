<?php

namespace Typhoeus\JleversSpapi\Models\MongoDB\Jobs;

use Typhoeus\JleversSpapi\Models\MongoAmazonSpBaseModel;

class JobMonitoring extends MongoAmazonSpBaseModel
{
	protected $database = 'amazon_sp';
	protected $collection = 'job_monitoring';
	protected $guarded = [];
    protected $fillable = [
        'seller',
        'sku',
        'job_name',
        'status',
        'message',
        'execution_time',
        'created_at'
    ];

	public $timestamps = true;
	public $incrementing = true;
}
