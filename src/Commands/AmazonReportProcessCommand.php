<?php

namespace Typhoeus\JleversSpapi\Commands;

use \Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Models\MongoDB\ProcessCommand;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLog;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLogItem;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\EmailNotification;
use Typhoeus\JleversSpapi\Traits\SchedulerLogger;
use Storage;

class AmazonReportProcessCommand extends Command
{
    use ConsoleOutput, EmailNotification, SchedulerLogger;

    protected $signature    = 'amz-spapi:report:process';
    protected $description  = 'Amazon Report Process';
    private $log_info = '';
    private $logs_data = [];

    public function __construct(
    ) {
        parent::__construct();
    }

    public function handle()
    {


        try {
            $website = env('APP_NAME');
            $rows = ProcessCommand::get();
            $files = [];

            foreach ($rows as $row) {
                $filename = str_replace(' ', '-', $row->name) . '.csv';
                $files[] = $this->createFile($website, $row->signature, $filename);
            }

            $subject = $website . ' - Report';
            $blade = 'amz-spapi::report-processes';
            $process = 'process-report';
            $data = [
                'rows' => $rows
            ];

            $this->sendMailWithAttachment($subject, $website, $data, $blade, $process, $files);

        } catch (Exception $e) {

            $result = json_encode($e->getMessage());
            $subject = 'ERROR - Report (' . $website . ')';
            $blade = 'amz-spapi::report-default';
            $process = 'error';
            $data = ['result' => $result];

            $this->sendMail($subject, $website, $data, $blade, $process);
        }

    }

    public function createFile($website, $signature, $filename)
    {
        $date_now = date('Y-m-d');
        $storage = $website . '\\' . $date_now . '\\';

        if(!Storage::exists($storage)){
            Storage::makeDirectory($storage);
        }

        $filePath = storage_path('app\\' . $storage . '' . $filename);
        $fp = fopen($filePath, 'w');

        $rows = SchedulerLog::whereWebsite($website)->whereSignature($signature)->where('process_start', 'LIKE', $date_now . '%')->orderBY('created_at', 'DESC')->get();

        fputcsv($fp, ['Process Start', 'Process End', 'Duration']);

        foreach ($rows as $row) {
            $data = [
                $row->process_start,
                $row->process_end,
                $row->process_duration,
            ];
            fputcsv($fp, $data);
        }

        fclose($fp);

        return $filePath;
    }

}
