<?php

namespace App;

class CsvWriter
{
    private $tempFileHandle;
    private string $tempFilePath;
    private string $finalFilePath;
    private bool $headersWritten = false;

    public function __construct(string $tempFilePath, string $finalFilePath)
    {
        $this->tempFilePath = $tempFilePath;
        $this->finalFilePath = $finalFilePath;
        $this->tempFileHandle = fopen($this->tempFilePath, 'w');
        if ($this->tempFileHandle === false) {
            throw new \Exception("Cannot open temporary CSV file for writing: {$this->tempFilePath}");
        }
        fwrite($this->tempFileHandle, "\xEF\xBB\xBF");
    }

    public function writeRows(array $rows): void
    {
        if (!$this->headersWritten && !empty($rows)) {
            // CSV headers
            $utf8Headers = array_map(fn($value) => mb_convert_encoding($value, 'UTF-8', 'auto'), array_keys($rows[0]));
            fputcsv($this->tempFileHandle, $utf8Headers, ';', '"', "\\");
            $this->headersWritten = true;
        }

        foreach ($rows as $row) {
            $utf8Row = array_map(fn($value) => mb_convert_encoding($value, 'UTF-8', 'auto'), $row);
            fputcsv($this->tempFileHandle, $utf8Row, ';', '"', "\\");
        }
    }

    public function close(): void
    {
        fclose($this->tempFileHandle);

        // Hernoem het tijdelijke bestand naar het definitieve bestand
        if (!rename($this->tempFilePath, $this->finalFilePath)) {
            throw new \Exception("Failed to rename temporary CSV file to final file.");
        }
    }
}
