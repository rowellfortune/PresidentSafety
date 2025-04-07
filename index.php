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

// Laad omgevingsvariabelen uit het .env-bestand
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Ophalen van omgevingsvariabelen
$bearerToken = $_ENV['BEARER_TOKEN'];
$sleepTime   = isset($_ENV['SLEEP_TIME']) ? (int) $_ENV['SLEEP_TIME'] : 0;
$testMode    = isset($_ENV['TEST_MODE']) && filter_var($_ENV['TEST_MODE'], FILTER_VALIDATE_BOOLEAN);

if ($sleepTime) echo "Wachten voor {$sleepTime} seconden tussen verzoeken...\n";

// Initialiseer de logger
$logger = new Logger();

// Initialiseer de API-client met de logger
$apiBaseUrl = 'https://data.presidentsafety.nl/api';
$apiClient  = new ApiClient($apiBaseUrl, $bearerToken, $logger);

try {
    // Controleer of de 'data' map bestaat, zo niet, maak deze aan
    $dataDirectory = __DIR__ . '/data';
    if (!is_dir($dataDirectory)) {
        mkdir($dataDirectory, 0755, true);
    }

    // Stel het pad in voor het tijdelijke en het definitieve CSV bestand
    $finalCsvFilePath = $dataDirectory . '/combined.csv';
    $tempCsvFilePath = $dataDirectory . '/temp.csv';

    // Open de CSV-writer met het tijdelijke bestand
    $csvWriter = new CsvWriter($tempCsvFilePath, $finalCsvFilePath);

    // Ophalen van merken
    $packageData = $apiClient->getPackage();
    $brands      = $packageData[0]['brands'];

    // Testmodus: gebruik slechts 2 merken
    if ($testMode) {
        $brands = array_slice($brands, 0, 2);
        echo "Testmodus ingeschakeld: verwerken van de eerste 2 merken.\n";
    }

    $totalBrands = count($brands);
    $progressBar = new ProgressBar($totalBrands);

    foreach ($brands as $brand) {
        // Ophalen van producten
        $products = $apiClient->getProductsByBrand($brand);
        checkRateLimit($apiClient);
        sleep($sleepTime);

        // Ophalen van voorraad
        $stocks = $apiClient->getStocksByBrand($brand);
        checkRateLimit($apiClient);
        sleep($sleepTime);

        // Ophalen van prijzen
        $prices = $apiClient->getPricesByBrand($brand);
        checkRateLimit($apiClient);
        sleep($sleepTime);

        // Data combineren
        $dataProcessor = new DataProcessor();
        $combinedData = $dataProcessor->combineData($products, $prices, $stocks, $brand);

        // Schrijf de gecombineerde data naar het CSV-bestand
        $csvWriter->writeRows($combinedData);

        // Update de voortgangsbalk
        $progressBar->advance();

        // Wacht tussen merken
        sleep($sleepTime);
    }

    // Sluit de CSV-writer (dit zal het tijdelijke bestand hernoemen naar het definitieve bestand)
    $csvWriter->close();

    echo "\nAlle data succesvol opgehaald en opgeslagen in '{$finalCsvFilePath}'.\n";

    // Toon de gelogde berichten
    echo "\nDetails van de uitgevoerde API-aanroepen:\n";
    $logger->output();

} catch (Exception $e) {
    echo 'Er is een fout opgetreden: ' . $e->getMessage() . "\n";
    // Optioneel: verwijder het tijdelijke bestand bij een fout
    if (isset($csvWriter) && file_exists($tempCsvFilePath)) {
        unlink($tempCsvFilePath);
    }
}

// Functie om de rate limit te controleren en indien nodig te wachten
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
