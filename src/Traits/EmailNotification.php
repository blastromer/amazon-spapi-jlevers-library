<?php

namespace Typhoeus\JleversSpapi\Traits;

use Illuminate\Support\Facades\Mail;
use Typhoeus\JleversSpapi\Models\MongoDB\EmailRecipient;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLog;
use Typhoeus\JleversSpapi\Models\MongoDB\SchedulerLogItem;

trait EmailNotification {

    public function sendMail($subject, $website, $data, $blade, $process)
    {
        $emails = EmailRecipient::where('process', $process)->get();
        $email_to = $emails->where('type', 'to')->pluck('email')->toArray();
        $email_cc = $emails->where('type', 'cc')->pluck('email')->toArray();
        $compact = compact('subject', 'website', 'data');

        Mail::send($blade, $compact, function ($m) use ($subject, $website, $email_to, $email_cc) {
            $m->to($email_to);
            $m->cc($email_cc);
            $m->from('amazon_spapi@plumbersstock.com', $website);
            $m->subject($subject);
        });
    }

    public function sendMailWithAttachment($subject, $website, $data, $blade, $process, $files = [])
    {
        $emails = EmailRecipient::where('process', $process)->get();
        $email_to = $emails->where('type', 'to')->pluck('email')->toArray();
        $email_cc = $emails->where('type', 'cc')->pluck('email')->toArray();
        $compact = compact('subject', 'website', 'data');

        Mail::send($blade, $compact, function ($m) use ($subject, $website, $email_to, $email_cc, $files) {
            $m->to($email_to);
            $m->cc($email_cc);
            $m->from('amazon_spapi@plumbersstock.com', $website);
            $m->subject($subject);

            foreach ($files as $file) {
                $m->attach($file);
            }
        });
    }
}
