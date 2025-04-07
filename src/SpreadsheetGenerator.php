<?php

namespace App;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SpreadsheetGenerator
{
    private array $data;
    private Spreadsheet $spreadsheet;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->spreadsheet = new Spreadsheet();
    }

    public function generate(): Spreadsheet
    {
        $sheet = $this->spreadsheet->getActiveSheet();

        // Kolomkoppen instellen
        $headers = [
            'Material Number',
            'EAN',
            'Description SAP',
            'Description',
            'Unit',
            'Delivery Unit',
            'Product Hierarchy 1',
            'Product Hierarchy 2',
            'Product Hierarchy 3',
            'Size',
            'Color',
            'Type',
            'Web Text',
            'Bruto Weight',
            'Netto Weight',
            'Stock Keeping',
            'Image URL',
            'Stock',
            'Price',
        ];

        $sheet->fromArray($headers);

        // Gegevens invullen
        $row = 2;
        foreach ($this->data as $data) {
            $sheet->setCellValue("A$row", $data['material_number']);
            $sheet->setCellValue("B$row", $data['ean']);
            $sheet->setCellValue("C$row", $data['description_sap']);
            $sheet->setCellValue("D$row", $data['description']);
            $sheet->setCellValue("E$row", $data['unit']);
            $sheet->setCellValue("F$row", $data['delivery_unit']);
            $sheet->setCellValue("G$row", $data['product_hierarchy_1']);
            $sheet->setCellValue("H$row", $data['product_hierarchy_2']);
            $sheet->setCellValue("I$row", $data['product_hierarchy_3']);
            $sheet->setCellValue("J$row", $data['size']);
            $sheet->setCellValue("K$row", $data['color']);
            $sheet->setCellValue("L$row", $data['type']);
            $sheet->setCellValue("M$row", strip_tags($data['web_text']));
            $sheet->setCellValue("N$row", $data['bruto_weight']);
            $sheet->setCellValue("O$row", $data['netto_weight']);
            $sheet->setCellValue("P$row", $data['stock_keeping']);
            $sheet->setCellValue("Q$row", $data['image_url']);
            $sheet->setCellValue("R$row", $data['stock']);
            $sheet->setCellValue("S$row", $data['price']);

            $row++;
        }

        return $this->spreadsheet;
    }
}
