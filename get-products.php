<?php

// Functie om het juiste pad naar 'vendor/autoload.php' te vinden
function findAutoloadFile()
{
    $paths = [
        __DIR__ . '/vendor/autoload.php', // Probeer in dezelfde map
        __DIR__ . '/../vendor/autoload.php', // Probeer een niveau omhoog
        __DIR__ . '/../../vendor/autoload.php', // Probeer twee niveaus omhoog
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    // Als het bestand niet wordt gevonden, geef een foutmelding
    die("Fout: 'vendor/autoload.php' niet gevonden. Controleer of Composer is geïnstalleerd en de benodigde libraries zijn geïnstalleerd.\n");
}

// Zoek het juiste pad naar 'vendor/autoload.php'
require findAutoloadFile();

// Hier volgt de rest van je code die PhpSpreadsheet gebruikt

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function keepColumnsInXLSX($inputFile, $outputFile, $columnsToKeep)
{
    // Laad het spreadsheet bestand
    $spreadsheet = IOFactory::load($inputFile);
    $worksheet = $spreadsheet->getActiveSheet();

    // Haal het aantal kolommen op in het werkblad
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

    // Maak een lijst van alle kolommen in het werkblad
    $allColumns = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $allColumns[] = Coordinate::stringFromColumnIndex($col);
    }

    // Bereken de kolommen die verwijderd moeten worden (alle kolommen behalve die in $columnsToKeep)
    $columnsToRemove = array_diff($allColumns, $columnsToKeep);

    // Sorteren van de kolommen van groot naar klein (zodat we geen indexverschuivingen krijgen bij het verwijderen)
    rsort($columnsToRemove);

    // Verwijder de kolommen die niet in de bewaar-lijst staan
    foreach ($columnsToRemove as $column) {
        $worksheet->removeColumn($column);
    }

    // Sla het gewijzigde bestand op
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($outputFile);

    echo "Kolommen succesvol behouden en bestand opgeslagen als $outputFile.\n";
}

// Input XLSX bestand
$inputFile = 'https://schoononline.katanapim.com/content/feeds/lw-schoononline-prijzen.xlsx';

// Output XLSX bestand
$outputFile = 'output.xlsx';

// Kolommen die je wilt behouden, bijvoorbeeld: 'A', 'C', 'E'
$columnsToKeep = ['A', 'C', 'E'];

// Voer de functie uit om de kolommen te behouden
keepColumnsInXLSX($inputFile, $outputFile);

?>