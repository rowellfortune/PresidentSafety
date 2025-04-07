<?php

namespace App;

class StockFetcher
{
    private ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function getAllStocks(array $brands): array
    {
        $allStocks = [];

        foreach ($brands as $brand) {
            $stocks = $this->apiClient->getStocksByBrand($brand);
            $allStocks += $stocks; // Sleutels behouden
        }

        return $allStocks;
    }
}
