<?php

namespace Typhoeus\JleversSpapi\Commands;

use Illuminate\Console\Command;
use Typhoeus\JleversSpapi\Services\Listing;
use Typhoeus\JleversSpapi\Traits\ConsoleOutput;
use Typhoeus\JleversSpapi\Traits\TimeStamp;
use Illuminate\Support\Facades\Storage;

class MatchInactiveCommand extends Command
{
    use ConsoleOutput, TimeStamp;

    protected $signature = 'amz-spapi:match:inactive:products';
    protected $description = 'This command will patch or change the specific field';

    protected $listing;

    public function __construct(Listing $listing)
    {
        parent::__construct();
        $this->listing = $listing;
    }

    public function handle()
    {
        $this->info('Matching Inactive products...');

        $fileName = "inactive/Inactive+Listings+Report+02-02-2025.txt";

        if (!Storage::exists($fileName)) {
            $this->error("File {$fileName} not found.");
            return;
        }

        $fullDir = Storage::path($fileName);
        $lines = file($fullDir, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (empty($lines)) {
            $this->error('The file is empty.');
            return;
        }

        // Parse the header to get the field names
        $headerLine = array_shift($lines);
        $headers = str_getcsv($headerLine, "\t");

        $arrayData = [];

        foreach (array_slice($lines, 0, 3) as $line) {
            $rowData = str_getcsv($line, "\t");
            $rowAssoc = [];
            
            foreach ($headers as $index => $field) {
                if ($field == 'seller-sku') {
                    
                }
                $rowAssoc[$field] = $rowData[$index] ?? null;
            }

            $arrayData[] = $rowAssoc;
        }

        dump($arrayData);
    }
}
