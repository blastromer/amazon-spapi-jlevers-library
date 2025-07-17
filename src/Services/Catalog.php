<?php

namespace Typhoeus\JleversSpapi\Services;

use Typhoeus\JleversSpapi\Http\Request;

class Catalog extends SpapiService
{
    public function getSuggestedASIN($dataArray = [], $marketplaceId = "", $sellerId = "")
    {
        $upc            = $dataArray['UPC'] ?? "";
        $codeType       = $this->identifyBarcodeType($upc);
        switch ($codeType) {
            case 'UPC':
                $indentifier = "UPC";
                break;

            default:
                $indentifier = "EAN";
                break;
        }

        $description    = str_replace(' ', ',', ($dataArray['Description'] ?? ''));
        $brand          = $dataArray['brand'] ?? null;
        $mpn            = $dataArray['mpn'] ?? null;
        $keywords       = str_replace(' ', ',', ($dataArray['keywords'] ?? '')) ?? null;
        $description   .= "," . $upc;
        $description   .= "," . $keywords;
        // dd($upc);
        $catalog    = new Request($this->seller->configurations(), $this->getCatalogMethod()); // this is working for update price
        try {
            $response = $catalog->getAPIinstance()->searchCatalogItems(
                $marketplaceId,
                $upc,
                $indentifier,
                'summaries,attributes',
                'en_US',
                null,
                null,
                null,
                null,
                10,
                null,
                null
            );
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]]; // Set response to null to prevent breaking the process
            return $response;
        }
    }

    public function getCatalogByASIN(array $asins)
    {
        $request        = new Request($this->seller->configurations(), $this->getCatalogMethod());
        foreach ($asins as $asin) {
            $response = $request->getAPIinstance()->getCatalogItem($asin, ['ATVPDKIKX0DER'], 'attributes');
            dd($response);
        }
        return $asins;
    }

    public function getCatalogItemByASIN($asin)
    {
        $request  = new Request($this->seller->configurations(), $this->getCatalogMethod());
        try {
            $response = $request->getAPIinstance()->getCatalogItem($asin, ['ATVPDKIKX0DER'], ['attributes','summaries']);
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response ?? [];
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]];
            return $response;
        }
    }

    public function getProductTypeByAsin($asin)
    {
        $request  = new Request($this->seller->configurations(), 'CatalogItemsV0Api');
        try {
            $response = $request->getAPIinstance()->getCatalogItem('ATVPDKIKX0DER', $asin);
            $attribute = $response->getPayload()->getAttributeSets()[0];
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $attribute['product_type_name'];
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]]; // Set response to null to prevent breaking the process
            return $response;
        }
    }

    public function getCatalogDetailsByAsin($asin)
    {
        $request  = new Request($this->seller->configurations(), 'CatalogItemsV0Api');
        $response = $request->getAPIinstance()->getCatalogItem('ATVPDKIKX0DER', $asin);

        return $response;
    }

    public function getCatalogItemList($upcEan)
    {
        $upc        = $this->formatUPC($upcEan);
        $ean        = $this->formatEAN($upcEan);
        $catalog    = new Request($this->seller->configurations(), 'CatalogItemsV0Api');
        try {
            $response = $catalog->getAPIinstance()->listCatalogItems(
                'ATVPDKIKX0DER',
                null,
                null,
                null,
                $upc,
                $ean,
                null,
                null
            );
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response ?? [];
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]];
            return $response;
        }
    }

    public function searchAsinByIndentifier($upcEan)
    {
        $upc        = $this->formatUPC($upcEan);
        $ean        = $this->formatEAN($upcEan);
        $catalog    = new Request($this->seller->configurations(), $this->getCatalogMethod()); // this is working for update price
        try {
            $response = $catalog->getAPIinstance()->searchCatalogItems(
                'ATVPDKIKX0DER',
                $ean,
                'ean',
                'summaries,attributes',
                'en_US',
                null,
                null,
                null,
                null,
                10,
                null,
                null
            );
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]]; // Set response to null to prevent breaking the process
            return $response;
        }
    }

    public function searchAsinByItemName($itemName)
    {
        $catalog    = new Request($this->seller->configurations(), $this->getCatalogMethod()); // this is working for update price
        try {
            $response = $catalog->getAPIinstance()->searchCatalogItems(
                'ATVPDKIKX0DER',
                null,
                null,
                'summaries,attributes',
                'en_US',
                null,
                $itemName,
                null,
                null,
                1,
                null,
                null
            );
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]]; // Set response to null to prevent breaking the process
            return $response;
        }
    }

    public function formatUPC($upc) {
        // Ensure the UPC is exactly 12 digits
        if (strlen($upc) == 12 && is_numeric($upc)) {
            return $upc;  // Valid UPC, return as is
        }

        // If not a valid UPC (not 12 digits), return null
        return null;  // Invalid UPC, return null
    }

    public function formatEAN($ean) {
        // Ensure the EAN is exactly 13 digits
        if (strlen($ean) == 13 && is_numeric($ean)) {
            return $ean;  // Valid EAN-13, return as is
        }

        // If not a valid EAN-13 (less than 13 digits), pad it with leading zeros to make it 13 digits
        return str_pad($ean, 13, '0', STR_PAD_LEFT);  // Handle as EAN
    }

    public function identifyBarcodeType($code)
    {
        $length = strlen($code);

        if ($length === 12) {
            return 'UPC';
        } elseif ($length === 13) {
            return 'EAN';
        } elseif ($length === 14) {
            return 'GTIN';
        }

        return 'Unknown';
    }

    public function getAsin($suggestedCatalog)
    {
        $payload            = $suggestedCatalog->getPayload() ?? null;
        $item               = isset($payload->getItems()[0]) ? $payload->getItems()[0] : null;
        $marketplaceAsin    = !is_null($item) ? $item->getIdentifiers()['marketplace_asin'] : null;
        $newAsin            = isset($marketplaceAsin['asin']) ? $marketplaceAsin['asin'] : null;

        return $newAsin;
    }

    public function searchUpcByIndentifier($upcEan)
    {
        $upc        = $this->formatUPC($upcEan);
        $request    = new Request($this->seller->configurations(), $this->getListingMethod());
        try {
            $response = $request->getAPIinstance()->searchCatalogItems(
                'ATVPDKIKX0DER',
                $upc,
                'ean',
                'summaries,attributes',
                'en_US',
                null,
                'A2G5859HCU1M8W',
                null,
                null,
                10,
                null,
                null
            );
            if (!isset($response) || empty($response)) {
                throw new Exception("Empty or invalid response received.");
            }
            return $response;
        } catch (\Exception $e) {
            $response = ['error' => ['message' => $e->getMessage()]]; // Set response to null to prevent breaking the process
            return $response;
        }
    }
}