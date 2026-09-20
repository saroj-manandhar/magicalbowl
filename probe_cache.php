<?php
header('Content-Type: text/plain');

echo "=== CATALOG CONFIG ===\n";
if (file_exists(__DIR__ . '/config.php')) {
    $lines = file(__DIR__ . '/config.php');
    foreach ($lines as $line) {
        if (strpos($line, 'DIR_STORAGE') !== false || strpos($line, 'DIR_CACHE') !== false || strpos($line, 'HTTP_SERVER') !== false) {
            echo trim($line) . "\n";
        }
    }
}

echo "\n=== ADMIN CONFIG ===\n";
if (file_exists(__DIR__ . '/msbadmin/config.php')) {
    $lines = file(__DIR__ . '/msbadmin/config.php');
    foreach ($lines as $line) {
        if (strpos($line, 'DIR_STORAGE') !== false || strpos($line, 'DIR_CACHE') !== false || strpos($line, 'HTTP_SERVER') !== false) {
            echo trim($line) . "\n";
        }
    }
}

echo "\n=== TMD EXPORT TWIG ===\n";
$twig_file = __DIR__ . '/extension/tmd/admin/view/template/other/export.twig';
if (file_exists($twig_file)) {
    echo "Size: " . filesize($twig_file) . " bytes\n";
    echo "First 200 chars:\n" . substr(file_get_contents($twig_file), 0, 200) . "\n";
    echo "Has form-export: " . (strpos(file_get_contents($twig_file), 'form-export') !== false ? 'YES' : 'NO') . "\n";
} else {
    echo "NOT FOUND: $twig_file\n";
}

echo "\n=== TMD EXPORT CONTROLLER ===\n";
$ctrl_file = __DIR__ . '/extension/tmd/admin/controller/other/export.php';
if (file_exists($ctrl_file)) {
    echo "Size: " . filesize($ctrl_file) . " bytes\n";
    echo "Has cfiled: " . (strpos(file_get_contents($ctrl_file), 'cfiled') !== false ? 'YES' : 'NO') . "\n";
    echo "Has valid_cols: " . (strpos(file_get_contents($ctrl_file), 'valid_cols') !== false ? 'YES' : 'NO') . "\n";
} else {
    echo "NOT FOUND: $ctrl_file\n";
}

echo "\n=== CACHE DIRECTORIES ===\n";
$potential_caches = [
    __DIR__ . '/storage/cache/template/',
    __DIR__ . '/system/storage/cache/template/',
    '/var/www/storage/cache/template/',
    '/home/magicalsingingbowls/storage/cache/template/',
    '/home/magicalsingingbowls/htdocs/storage/cache/template/'
];
foreach ($potential_caches as $pc) {
    if (is_dir($pc)) {
        $count = count(glob($pc . '*'));
        echo "Found dir: $pc (items: $count)\n";
    }
}
