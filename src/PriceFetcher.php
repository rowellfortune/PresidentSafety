<?php

namespace App;

class PriceFetcher
{
    private ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function getAllPrices(array $brands): array
    {
        $allPrices = [];

        foreach ($brands as $brand) {
            $prices = $this->apiClient->getPricesByBrand($brand);
            $allPrices += $prices; // Sleutels behouden
        }

        return $allPrices;
    }
}
