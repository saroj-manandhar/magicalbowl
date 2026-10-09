<?php
header('Content-Type: text/plain');
echo "=== SERVER GIT STATUS ===\n";
if (file_exists(__DIR__ . '/.git/HEAD')) {
    echo "HEAD: " . trim(file_get_contents(__DIR__ . '/.git/HEAD')) . "\n";
    $head = trim(file_get_contents(__DIR__ . '/.git/HEAD'));
    if (strpos($head, 'ref: ') === 0) {
        $ref = trim(substr($head, 5));
        if (file_exists(__DIR__ . '/.git/' . $ref)) {
            echo "COMMIT: " . trim(file_get_contents(__DIR__ . '/.git/' . $ref)) . "\n";
        }
    }
} else {
    echo ".git/HEAD not found\n";
}

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

$pids = isset($_GET['pid']) ? array_map('intval', explode(',', $_GET['pid'])) : [3588, 3589, 3590, 4017];
$pids_str = implode(',', $pids);
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

echo "\n=== PRODUCT DATA ($pids_str) ===\n";
if (file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
    $link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
    if ($link) {
        $where = "p.product_id IN ($pids_str)";
        if ($q !== '') {
            $q_esc = mysqli_real_escape_string($link, $q);
            $where .= " OR p.model LIKE '%$q_esc%' OR pd.name LIKE '%$q_esc%'";
        }
        $res = mysqli_query($link, "SELECT p.product_id, p.model, p.status, p.weight, p.weight_class_id, p.length, p.width, p.height, pd.name, pd.diameter FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id WHERE ($where) AND pd.language_id = 1");
        while ($row = mysqli_fetch_assoc($res)) {
            echo "ID: " . $row['product_id'] . " | Model: " . $row['model'] . " | Status: " . $row['status'] . " | Name: " . $row['name'] . "\n";
            echo "  Weight: " . $row['weight'] . " | WeightClass: " . $row['weight_class_id'] . " | Dimensions: " . $row['length'] . "x" . $row['width'] . "x" . $row['height'] . "\n";
            echo "  Diameter: " . ($row['diameter'] ? substr($row['diameter'], 0, 100) . '...' : 'EMPTY') . "\n";
            $seo_res = mysqli_query($link, "SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE `key` = 'product_id' AND `value` = '" . (int)$row['product_id'] . "'");
            $seo_row = mysqli_fetch_assoc($seo_res);
            echo "  SEO URL: " . ($seo_row ? $seo_row['keyword'] : 'NONE') . "\n";
            $attr_res = mysqli_query($link, "SELECT * FROM " . DB_PREFIX . "product_attribute WHERE product_id = " . (int)$row['product_id']);
            echo "  Attributes count: " . mysqli_num_rows($attr_res) . "\n\n";
        }
    } else {
        echo "DB connection failed: " . mysqli_connect_error() . "\n";
    }
}

