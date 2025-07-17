<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Request;
use Symfony\Component\Console\Output\ConsoleOutput as Console;
use Symfony\Component\Console\Helper\ProgressBar;
use SellingPartnerApi\Model\ReportsV20210630\CreateReportSpecification;
use SellingPartnerApi\Model\ReportsV20210630\CreateReportResponse;
use SellingPartnerApi\ReportType;
use SellingPartnerApi\Document;
use Storage;

class Report extends SpapiService
{
    public function listAllItemsReport()
    {
        $reportType     = ReportType::GET_MERCHANT_LISTINGS_ALL_DATA;
        $report         = $this->make($reportType);

        return $report;
    }

    public function listActiveItemsReport()
    {
        $reportType     = ReportType::GET_MERCHANT_LISTINGS_DATA;
        $report         = $this->make($reportType);

        return $report;
    }

    public function listInactiveItemsReport()
    {
        $reportType     = ReportType::GET_MERCHANT_LISTINGS_INACTIVE_DATA;
        $report         = $this->make($reportType);

        return $report;
    }

    public function make($type)
    {
        $request    = new Request($this->seller->configurations(), $this->getReportMethod());
        $body = new CreateReportSpecification([
            'report_type' => $type['name'],
            'marketplace_ids' => ['ATVPDKIKX0DER'],
        ]);

        $response   = $request->getAPIinstance()->createReport($body);

        return $response;
    }

    public function getById($id, $defaultDir, $fileDir)
    {
        $type       = ReportType::GET_MERCHANT_LISTINGS_ALL_DATA;
        $request    = new Request($this->seller->configurations(), $this->getReportMethod());
        $response   = $request->getAPIinstance()->getReport($id);
        dump($response['processing_status'] . "...");
        if ($response->getProcessingStatus() === 'DONE') {
            $documentUrl = $response->getReportDocumentId();
            $reportData = $request->getAPIinstance()->getReportDocument($documentUrl);
            $doc = new Document($reportData, $type);
            if (Storage::exists($fileDir)) {
                Storage::delete($fileDir);
            }
            if (!Storage::put($fileDir, $doc->download())) {
                return false;
            }
            if (Storage::exists($defaultDir)) {
                Storage::delete($defaultDir);
            }
            Storage::copy($fileDir, $defaultDir);

            return true;
        } else {
            sleep(5);
            $this->getById($id, $defaultDir, $fileDir);
        }

        return true;
    }
}