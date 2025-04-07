<?php

namespace App;

class DataProcessor
{
    public function combineData(array $products, array $prices, array $stocks, string $brand): array
    {
        $combinedData = [];

        foreach ($products as $product) {
            $materialNumber = $product['material_number'] ?? '';
            $deliveryUnit = $product['delivery_unit'] ?? 1;
            $price = $prices[$materialNumber] ?? 0;

            // Nieuwe kolom "sku" met prefix "PS"
            $sku = 'PS' . $materialNumber;

            // Berekening voor "sales_price": price * delivery_unit
            $salesPrice = $price * $deliveryUnit;

            // Nieuwe kolom "product_cat"
            $productCat = 'PBM';
            if (!empty($product['product_hierarchy_1'])) {
                $productCat .= ' > ' . $product['product_hierarchy_1'];
            }
            if (!empty($product['product_hierarchy_2'])) {
                $productCat .= ' > ' . $product['product_hierarchy_2'];
            }

            $combinedData[] = [
                'material_number'        => $materialNumber,
                'ean'                    => $product['ean'] ?? '',
                'sku'                    => $sku, // Nieuwe kolom
                'brand'                  => $brand,
                'description_sap'        => $product['description_sap'] ?? '',
                'description'            => $product['description'] ?? '',
                'unit'                   => $product['unit'] ?? '',
                'delivery_unit'          => $deliveryUnit,
                'sales_price'            => $salesPrice, // Nieuwe kolom na delivery_unit
                'product_cat'            => $productCat, // Nieuwe kolom vóór product_hierarchy_1
                'product_hierarchy_1'    => $product['product_hierarchy_1'] ?? '',
                'product_hierarchy_2'    => $product['product_hierarchy_2'] ?? '',
                'product_hierarchy_3'    => $product['product_hierarchy_3'] ?? '',
                'size'                   => $product['size'] ?? '',
                'color'                  => $product['color'] ?? '',
                'type'                   => $product['type'] ?? '',
                'price_article'          => $product['price_article'] ?? '',
                'bruto_weight'           => $product['bruto_weight'] ?? '',
                'netto_weight'           => $product['netto_weight'] ?? '',
                'stock_keeping'          => $product['stock_keeping'] ?? '',
                'image_url'              => $product['image_url'] ?? '',
                'price'                  => $price,
                'stock'                  => $stocks[$materialNumber] ?? '',
                'web_text'               => $product['web_text'] ?? '',
            ];
        }

        return $combinedData;
    }
}
