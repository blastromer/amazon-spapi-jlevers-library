<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Typhoeus\JleversSpapi\Models\MySql\SdShipment;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PullHistoricalDataCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:shipments:pull-historical';
    protected $description = 'This command will patch or change the specific field';

    public function __construct(SdShipment $sdShipment)
    {
        parent::__construct();
        $this->sdShipment = $sdShipment;
    }

    public function handle()
    {
        $lastYear = Carbon::now()->subYear()->year;
        $shipments = $this->sdShipment->whereYear('created_at', $lastYear)
            ->whereBetween('weight', [50, 150])
            ->where('BillTo', '!=', 'AdamsAndCo')
            ->get();
            // dd($shipments->count());
        $i = 1;
        foreach ($shipments as $shipment) {
            $report = "";
            $report .= $i++ . ". \t";
            $report .= $shipment->getProductId() . "\t";
            $report .= $shipment->getEclipseId() . "\t";
            $report .= $shipment->getQty() . "\t";
            $report .= $shipment->getWeight() . "lbs \t";
            $report .= $shipment->getShippingCost() . "\t";
            $report .= $shipment->getShippedDate() . "\t";
            \Log::info($report);
            $this->info($report);
        }
    }
}