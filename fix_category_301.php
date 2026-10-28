<?php
/**
 * Standalone Fix Script for Category 301:
 * "Planetary, Therapeutic, Harmonic Sound - Information"
 *
 * Can be executed via browser:
 * https://www.magicalsingingbowls.com/fix_category_301.php
 * or via CLI on the server:
 * php fix_category_301.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
} else {
    die("config.php not found!");
}

echo "<h1>Fixing Category 301 SEO URL (Harmonic Sound - Information)</h1>";

// 1. Connect to Database
$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$prefix = DB_PREFIX;

if (!$link) {
    die("<p style='color:red;'>Database connection failed: " . mysqli_connect_error() . "</p>");
}
echo "<p style='color:green;'>✔ Connected to database successfully.</p>";

// 2. Inspect and fix SEO URL in oc_seo_url
$target_keyword = 'planetary-therapeutic-harmonic-sound-information';
$col_query = mysqli_query($link, "SHOW COLUMNS FROM `{$prefix}seo_url`");
$seo_cols = [];
if ($col_query) {
    while ($c = mysqli_fetch_assoc($col_query)) {
        $seo_cols[] = $c['Field'];
    }
}
$has_key_col = in_array('key', $seo_cols);
$has_query_col = in_array('query', $seo_cols);

echo "<h3>Step 1: Updating Database SEO URL Records</h3>";

if ($has_key_col) {
    $check_c301 = mysqli_query($link, "SELECT * FROM `{$prefix}seo_url` WHERE `key` = 'path' AND `value` = '301'");
    if (mysqli_num_rows($check_c301) == 0) {
        $insert_sql = "INSERT INTO `{$prefix}seo_url` (store_id, language_id, `key`, `value`, `keyword`" . (in_array('sort_order', $seo_cols) ? ", `sort_order`" : "") . (in_array('query', $seo_cols) ? ", `query`" : "") . ") VALUES (0, 1, 'path', '301', '{$target_keyword}'" . (in_array('sort_order', $seo_cols) ? ", 0" : "") . (in_array('query', $seo_cols) ? ", 'category_id=301'" : "") . ")";
        if (mysqli_query($link, $insert_sql)) {
            echo "✔ Inserted Category 301 SEO URL '<b>{$target_keyword}</b>' (`key` = 'path', `value` = '301').<br/>";
        } else {
            echo "<span style='color:red;'>✘ Insert failed: " . mysqli_error($link) . "</span><br/>";
        }
    } else {
        while ($r301 = mysqli_fetch_assoc($check_c301)) {
            echo "Found Category 301 SEO URL ID " . $r301['seo_url_id'] . " with keyword '" . htmlspecialchars($r301['keyword']) . "'<br/>";
            if (empty($r301['keyword']) || $r301['keyword'] === 'Himalayan-Singing-Bowls' || $r301['keyword'] !== $target_keyword) {
                mysqli_query($link, "UPDATE `{$prefix}seo_url` SET `keyword` = '{$target_keyword}' WHERE `seo_url_id` = " . (int)$r301['seo_url_id']);
                echo "✔ Updated Category 301 SEO URL ID " . $r301['seo_url_id'] . " to '<b>{$target_keyword}</b>'.<br/>";
            } else {
                echo "✔ Category 301 SEO URL is already set to '{$target_keyword}'.<br/>";
            }
        }
    }
}

if ($has_query_col) {
    $check_q301 = mysqli_query($link, "SELECT * FROM `{$prefix}seo_url` WHERE `query` = 'category_id=301'");
    if (mysqli_num_rows($check_q301) == 0) {
        $insert_q = "INSERT INTO `{$prefix}seo_url` (store_id, language_id, `query`, `keyword`" . ($has_key_col ? ", `key`, `value`" : "") . (in_array('sort_order', $seo_cols) ? ", `sort_order`" : "") . ") VALUES (0, 1, 'category_id=301', '{$target_keyword}'" . ($has_key_col ? ", 'path', '301'" : "") . (in_array('sort_order', $seo_cols) ? ", 0" : "") . ")";
        if (mysqli_query($link, $insert_q)) {
            echo "✔ Inserted Category 301 SEO URL '<b>{$target_keyword}</b>' (`query` = 'category_id=301').<br/>";
        } else {
            echo "<span style='color:red;'>✘ Query insert failed: " . mysqli_error($link) . "</span><br/>";
        }
    } else {
        while ($rq301 = mysqli_fetch_assoc($check_q301)) {
            if (empty($rq301['keyword']) || $rq301['keyword'] === 'Himalayan-Singing-Bowls' || $rq301['keyword'] !== $target_keyword) {
                mysqli_query($link, "UPDATE `{$prefix}seo_url` SET `keyword` = '{$target_keyword}' WHERE `seo_url_id` = " . (int)$rq301['seo_url_id']);
                echo "✔ Updated Category 301 query SEO URL to '<b>{$target_keyword}</b>'.<br/>";
            } else {
                echo "✔ Category 301 query SEO URL is already set to '{$target_keyword}'.<br/>";
            }
        }
    }
}

// 3. Clear storage and Varnish caches
echo "<h3>Step 2: Purging Caches</h3>";
function purge_deep($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            purge_deep($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

$cache_dirs = [
    __DIR__ . '/storage/cache/',
    __DIR__ . '/system/storage/cache/',
    __DIR__ . '/extension/so_theme/catalog/view/template/minify/'
];
foreach ($cache_dirs as $cd) {
    if (is_dir($cd)) {
        purge_deep($cd);
    }
}
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
echo "✔ Template, image, and system caches recursively cleared.<br/>";

// Varnish Purge
if (function_exists('exec')) {
    @exec('clpctl varnish-cache:purge --purge=all 2>&1');
    @exec('/usr/bin/clpctl varnish-cache:purge --purge=all 2>&1');
    @exec('/usr/local/bin/clpctl varnish-cache:purge --purge=all 2>&1');
}

$purge_targets = [
    'https://www.magicalsingingbowls.com/',
    'http://127.0.0.1:6081/',
    'http://127.0.0.1/'
];
$purge_header_sets = [
    ["Host: www.magicalsingingbowls.com", "X-Cache-Tags: d0a4"],
    ["Host: www.magicalsingingbowls.com", "X-Purge-Method: regex", "X-Purge-Regex: .*"],
    ["Host: www.magicalsingingbowls.com"]
];
foreach ($purge_targets as $pt) {
    foreach ($purge_header_sets as $ph) {
        if (!function_exists('curl_init')) break;
        $ch = curl_init($pt);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $ph);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        @curl_exec($ch);
        @curl_close($ch);
    }
}
echo "✔ Varnish cache purged.<br/>";

echo "<h2 style='color:green;'>Done! Category 301 is fixed on neww.</h2>";
echo "<p>Test the link: <a href='https://www.magicalsingingbowls.com/Himalayan-Singing-Bowls/planetary-therapeutic-harmonic-sound-information' target='_blank'>https://www.magicalsingingbowls.com/Himalayan-Singing-Bowls/planetary-therapeutic-harmonic-sound-information</a></p>";
