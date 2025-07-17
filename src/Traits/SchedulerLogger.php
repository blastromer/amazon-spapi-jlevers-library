<?php

namespace Typhoeus\JleversSpapi\Traits;

use Illuminate\Support\Facades\Mail;
use MongoDB\BSON\ObjectId;
use Typhoeus\JleversSpapi\Models\MongoDB\EmailRecipient;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLog;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLogItem;

trait SchedulerLogger {

    public function storeLogs($website, $signature, $process_start, $process_end, $items)
    {
        $log = SchedulerLog::create([
            'website' => $website,
            'signature' => $signature,
            'process_start' => $process_start,
            'process_end' => $process_end,
        ]);

        foreach ($items as $item) {
            if ($item['status'] == 'error') {
                SchedulerLogItem::create([
                    'scheduler_log_id' => new ObjectId($log->id),
                    'website' => $website,
                    'order_id' => $item['amazon_order_id'],
                    'message' => $item['message']
                ]);
            }
        }
    }
}
