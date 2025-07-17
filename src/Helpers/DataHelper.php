<?php

namespace Typhoeus\JleversSpapi\Helpers;

class DataHelper
{
    public function aggregateData($arraydata)
    {
        $itemSummary = $arraydata[0] ?? [];

        // Aggregating the data
        $aggregatedData = [
            'asin'            => $itemSummary['asin'],
            'marketplace'     => $itemSummary['marketplace_id'],
            'product_type'    => $itemSummary['product_type'],
            'condition'       => $itemSummary['condition_type'],
            'status'          => implode(', ', $itemSummary['status']),
            'item_name'       => $itemSummary['item_name'],
            'created_at'      => $itemSummary['created_date'],
            'updated_at'      => $itemSummary['last_updated_date'],
            'image_url'       => $itemSummary['main_image']['link'],
            'image_dimensions'=> [
                'height' => $itemSummary['main_image']['height'],
                'width'  => $itemSummary['main_image']['width']
            ]
        ];

        return $aggregatedData ?? [];
    }

    public function sanitizeData($data)
    {
        if (is_string($data)) {
            $clnData = strip_tags($data);
        }
        return $clnData;
    }
}