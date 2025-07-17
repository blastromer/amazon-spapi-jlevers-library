<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Models\MySql\SdOrder;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;

class AmazonOrderWithoEclipseIdCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi:report:order-with-no-eclipse-id';
    protected $description  = 'Report order with no eclipse id.';

    public function __construct(
    ) {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $process_start = Carbon::now()->toDateTimeString();

            $loop_bool = true;
            $website = env('APP_NAME');

            $rows = SdOrder::where('WebSite', $website)->whereIn('ShipStatus', ['New'])
                    ->where('EclipseId', '')
                    ->where('updated_at', '>', '2025-03-25')
                    ->whereRaw('NOW() >= DATE_ADD(updated_at, INTERVAL 3 HOUR)')
                    ->orderBy('OrderDate', 'DESC')
                    ->limit(100)
                    ->get(['EclipseId', 'OrderId', 'OrderDate', 'ShipStatus', 'updated_at']);

            $process_end = Carbon::now()->toDateTimeString();

            if ($rows->count() > 0) {
                $subject = 'ALERT!!! ' . $website . ' - Order with No Eclipse Id`s';
                $blade = 'amz-spapi::order-with-no-eclipse-id';
                $process = 'order-with-no-eclipse-id';
                $data = [
                    'signature' => $this->signature,
                    'process_start' => $process_start,
                    'process_end' => $process_end,
                    'rows' => $rows
                ];

                $this->sendMail($subject, $website, $data, $blade, $process);
            }
        } catch (Exception $e) {
            $result = $e->getMessage();
        }
    }
}
