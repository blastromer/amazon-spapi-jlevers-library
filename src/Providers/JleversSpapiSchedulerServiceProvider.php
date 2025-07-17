<?php

namespace Typhoeus\JleversSpapi\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Typhoeus\JleversSpapi\Helpers\AppHelper;
use Typhoeus\JleversSpapi\Models\MongoDB\Scheduler;

class JleversSpapiSchedulerServiceProvider extends ServiceProvider
{
    private $appName;
    private $scheduler;

    /**
     * @return void
     */
    public function register()
    {
        /**
         * Bind your service here
         */
    }

    /**
     * @return void
     */
    public function boot()
    {
        $this->appName      = $this->app->config['app.name'];
        $this->scheduler    = new Scheduler;
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $this->scheduleTasks($schedule, $this->appName);
        });
    }

    /**
     * Retrieve schedules from MongoDB and add them to Laravel Scheduler.
     */
    protected function scheduleTasks($schedule, $appName)
    {
        $schedules = $this->scheduler
            ->where('_id', $appName)
            ->where('is_active', true)
            ->get();
        foreach ($schedules as $task) {
            foreach ($task['schedules'] as $job) {
                if ($job['status'] != 'active') {
                    continue;
                }
                $schedule->command($job['command'])->cron($job['schedule']);
            }
        }
    }
}
