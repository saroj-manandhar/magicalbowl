<?php
// Test parsing the sample CSV files

$samples = [
    'country' => 'image/catalog/postage_country_sample.csv',
    'priority' => 'image/catalog/mce-priorityrate-sample.csv',
    'economy' => 'image/catalog/mce-economyrate-sample.csv'
];

foreach ($samples as $type => $path) {
    if (!is_file($path)) {
        echo "✘ File $path not found!\n";
        continue;
    }
    
    $handle = fopen($path, 'r');
    $row_count = 0;
    while (($row = fgetcsv($handle, 5000, ',')) !== false) {
        if (!empty($row) && count(array_filter($row, 'strlen')) > 0) {
            $row_count++;
        }
    }
    fclose($handle);
    echo "✔ Successfully parsed $type ($path): $row_count rows read.\n";
}
