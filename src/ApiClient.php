<?php

namespace App;

use Exception;

class ApiClient
{
    private string $baseUrl;
    private string $bearerToken;
    private array $rateLimitHeaders = [];
    private Logger $logger;

    public function __construct(string $baseUrl, string $bearerToken, Logger $logger)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->bearerToken = $bearerToken;
        $this->logger = $logger;
    }

    public function get(string $endpoint): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new Exception('Ongeldige URL: ' . $url);
        }

        $this->logger->log("Opvragen van URL: $url");

        $ch = curl_init($url);

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->bearerToken,
            ],
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $headerParts = explode(':', $header, 2);
                if (count($headerParts) < 2) {
                    return $len;
                }
                $key = strtolower(trim($headerParts[0]));
                $value = trim($headerParts[1]);
                $responseHeaders[$key] = $value;
                return $len;
            },
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Fout bij het ophalen van API-gegevens: ' . curl_error($ch));
        }

        curl_close($ch);

        $this->rateLimitHeaders = [
            'limit' => isset($responseHeaders['x-ratelimit-limit']) ? (int)$responseHeaders['x-ratelimit-limit'] : null,
            'remaining' => isset($responseHeaders['x-ratelimit-remaining']) ? (int)$responseHeaders['x-ratelimit-remaining'] : null,
            'reset' => isset($responseHeaders['x-ratelimit-reset']) ? (int)$responseHeaders['x-ratelimit-reset'] : null,
        ];

        if ($httpCode === 429) {
            $retryAfter = isset($responseHeaders['retry-after']) ? (int)$responseHeaders['retry-after'] : 60;
            $this->logger->log("Rate limit bereikt. Wachten voor {$retryAfter} seconden...");
            sleep($retryAfter);
            return $this->get($endpoint);
        }

        if ($httpCode !== 200) {
            throw new Exception("API-aanroep naar {$url} mislukt met statuscode {$httpCode}. Respons: {$response}");
        }

        $decodedResponse = json_decode($response, true);

        if ($decodedResponse === null) {
            throw new Exception('Fout bij het decoderen van API-respons: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    public function getRateLimitHeaders(): array
    {
        return $this->rateLimitHeaders;
    }

    public function getPackage(): array
    {
        return $this->get('/package');
    }

    public function getProductsByBrand(string $brand): array
    {
        $encodedBrand = rawurlencode($brand);
        return $this->get("/products/brand/{$encodedBrand}");
    }

    public function getStocksByBrand(string $brand): array
    {
        $encodedBrand = rawurlencode($brand);
        return $this->get("/stocks/brand/{$encodedBrand}");
    }

    public function getPricesByBrand(string $brand): array
    {
        $encodedBrand = rawurlencode($brand);
        return $this->get("/prices/brand/{$encodedBrand}");
    }
}
