<?php

ini_set('max_execution_time', 1800);
set_time_limit(1800);

require 'vendor/autoload.php';

use App\ApiClient;
use App\DataProcessor;
use App\CsvWriter;
use App\ProgressBar;
use App\Logger;
use Dotenv\Dotenv;

// Load .env config
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ENV
$bearerToken = $_ENV['BEARER_TOKEN'];
$sleepTime   = isset($_ENV['SLEEP_TIME']) ? (int) $_ENV['SLEEP_TIME'] : 0;
$testMode    = isset($_ENV['TEST_MODE']) && filter_var($_ENV['TEST_MODE'], FILTER_VALIDATE_BOOLEAN);

if ($sleepTime) echo "Wachten voor {$sleepTime} seconden tussen verzoeken...\n";

// Logger
$logger = new Logger();

// President API client
$apiBaseUrl = 'https://data.presidentsafety.nl/api';
$apiClient  = new ApiClient($apiBaseUrl, $bearerToken, $logger);

// KatanaPIM API call
function fetchKatanaProducts(): array
{
    $baseUrl = "https://schoononline.katanapim.com/api/v1/Product";
    $apiKey = "549703c3-d73a-4c0b-8e15-0a25569bece2";
    $pageSize = 100;
    $page = 1;
    $externalKeyToKorting = [];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey,
        'Accept: application/json'
    ]);

    do {
        $url = $baseUrl . "?filterModel.paging.pageSize=$pageSize&filterModel.paging.pageNumber=$page&filterModel.SpecFilters[0].SpecName=Korting%20op%20Inkoop";
        curl_setopt($ch, CURLOPT_URL, $url);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("Katana API error: " . curl_error($ch));
        }

        $data = json_decode($response, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new Exception("Unexpected Katana API response: $response");
        }

        foreach ($data['data'] as $product) {
            $sku = $product['ExternalKey'] ?? null;
            if (!$sku || empty($product['Specs'])) continue;

            foreach ($product['Specs'] as $spec) {
                if (
                    $spec['Name'] === 'Korting op Inkoop' &&
                    isset($spec['OptionCode'])
                ) {
                    $korting = floatval(str_replace(',', '.', $spec['OptionCode']));
                    $externalKeyToKorting[$sku] = $korting;
                    break;
                }
            }
        }

        if (count($data['data']) < $pageSize) break;
        $page++;
        sleep(1);
    } while (true);

    curl_close($ch);
    return $externalKeyToKorting;
}

// Rate limit checker
function checkRateLimit(ApiClient $apiClient): void
{
    $rateLimitHeaders = $apiClient->getRateLimitHeaders();
    if (isset($rateLimitHeaders['remaining']) && $rateLimitHeaders['remaining'] <= 1) {
        $resetTime = isset($rateLimitHeaders['reset']) ? (int)$rateLimitHeaders['reset'] : time() + 60;
        $waitTime = $resetTime - time();
        if ($waitTime > 0) {
            echo "\nRate limit bereikt. Wachten voor {$waitTime} seconden tot reset...\n";
            sleep($waitTime);
        }
    }
}

// Start main process
try {
    $dataDirectory = __DIR__ . '/data';
    if (!is_dir($dataDirectory)) {
        mkdir($dataDirectory, 0755, true);
    }

    $finalCsvFilePath = $dataDirectory . '/combined.csv';
    $tempCsvFilePath = $dataDirectory . '/temp.csv';

    $csvWriter = new CsvWriter($tempCsvFilePath, $finalCsvFilePath);

    echo "Fetching KatanaPIM korting mappings...\n";
    $katanaKortingMap = fetchKatanaProducts();

    $packageData = $apiClient->getPackage();
    $brands      = $packageData[0]['brands'];

    if ($testMode) {
        $brands = array_slice($brands, 0, 2);
        echo "Testmodus ingeschakeld: verwerken van de eerste 2 merken.\n";
    }

    $totalBrands = count($brands);
    $progressBar = new ProgressBar($totalBrands);

    foreach ($brands as $brand) {
        $products = $apiClient->getProductsByBrand($brand);
        checkRateLimit($apiClient);
        sleep($sleepTime);

        $stocks = $apiClient->getStocksByBrand($brand);
        checkRateLimit($apiClient);
        sleep($sleepTime);

        $prices = $apiClient->getPricesByBrand($brand);
        checkRateLimit($apiClient);
        sleep($sleepTime);

        $dataProcessor = new DataProcessor();
        $combinedData = $dataProcessor->combineData($products, $prices, $stocks, $brand);

        // Verrijk combinedData met korting & inkoopprijs
        foreach ($combinedData as &$row) {
            $sku = $row['sku'];
            $salesPrice = floatval($row['sales_price']);

            if (isset($katanaKortingMap[$sku])) {
                $korting = $katanaKortingMap[$sku];
                $inkoopprijs = $salesPrice - ($salesPrice * $korting);

                $row['korting_op_inkoop'] = $korting;
                $row['inkoopprijs'] = number_format($inkoopprijs, 2, '.', '');
                $logger->log("SKU $sku → korting: $korting → inkoopprijs: $inkoopprijs");
            } else {
                $row['korting_op_inkoop'] = '';
                $row['inkoopprijs'] = '';
            }
        }
        unset($row);

        $csvWriter->writeRows($combinedData);
        $progressBar->advance();
        sleep($sleepTime);
    }

    $csvWriter->close();

    echo "\n✅ Alles succesvol opgeslagen in '{$finalCsvFilePath}'.\n";
    echo "\n🔍 Log overzicht:\n";
    $logger->output();

} catch (Exception $e) {
    echo '❌ Fout opgetreden: ' . $e->getMessage() . "\n";
    if (isset($csvWriter) && file_exists($tempCsvFilePath)) {
        unlink($tempCsvFilePath);
    }
}