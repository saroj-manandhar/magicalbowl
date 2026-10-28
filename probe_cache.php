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

echo "\n=== CATEGORY DATA ===\n";
if (file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}
$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if (isset($_GET['cat']) && $link) {
    $cat_id = (int)$_GET['cat'];
    $cat_res = mysqli_query($link, "SELECT c.*, cd.name, cd.description, cd.meta_title FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id WHERE c.category_id = $cat_id");
    while ($row = mysqli_fetch_assoc($cat_res)) {
        echo "Category ID: " . $row['category_id'] . "\n";
        echo "  Name: " . $row['name'] . "\n";
        echo "  Parent ID: " . $row['parent_id'] . "\n";
        echo "  Status: " . $row['status'] . "\n";
        echo "  Image: " . $row['image'] . "\n";
        echo "  Meta Title: " . $row['meta_title'] . "\n";
        echo "  Desc length: " . strlen($row['description']) . "\n";
    }
    echo "  SEO URLs:\n";
    $seo_cat = mysqli_query($link, "SELECT * FROM " . DB_PREFIX . "seo_url WHERE (`key` = 'path' AND `value` = '$cat_id') OR `value` = '$cat_id' OR `query` LIKE '%$cat_id%'");
    while ($s = mysqli_fetch_assoc($seo_cat)) {
        echo "    ID: {$s['seo_url_id']} | Store: {$s['store_id']} | Lang: {$s['language_id']} | Key: {$s['key']} | Value: {$s['value']} | Keyword: {$s['keyword']}\n";
    }
    echo "  Category Paths:\n";
    $path_cat = mysqli_query($link, "SELECT * FROM " . DB_PREFIX . "category_path WHERE category_id = $cat_id ORDER BY level ASC");
    while ($p = mysqli_fetch_assoc($path_cat)) {
        echo "    Path ID: {$p['path_id']} | Level: {$p['level']}\n";
    }
    echo "  Parent Category SEO URLs:\n";
    $seo_p = mysqli_query($link, "SELECT * FROM " . DB_PREFIX . "seo_url WHERE (`key` = 'path' AND `value` = '27') OR `value` = '27'");
    while ($sp = mysqli_fetch_assoc($seo_p)) {
        echo "    Parent ID: {$sp['seo_url_id']} | Key: {$sp['key']} | Value: {$sp['value']} | Keyword: {$sp['keyword']}\n";
    }
}


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

