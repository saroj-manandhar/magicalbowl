<?php
// Remote patch script for catalog/controller/product/category.php, database, map, and cache

if (file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
} else {
    die("config.php not found!");
}

$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$prefix = DB_PREFIX;

if (!$link) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "<h2>1. Patching category.php File...</h2>";
$target_file = __DIR__ . '/catalog/controller/product/category.php';

if (file_exists($target_file)) {
    $content = file_get_contents($target_file);
    $old_code = '$custom_filter_categories = [20, 27, 150];';
    $new_code = '$custom_filter_categories = [27];';
    $old_if = 'if (in_array($category_id, $custom_filter_categories) || in_array($category_info[\'parent_id\'], $custom_filter_categories)) {';
    $new_if = 'if (in_array($category_id, $custom_filter_categories) || in_array($category_info[\'parent_id\'], $custom_filter_categories) || in_array(27, $parts)) {';

    if (strpos($content, $old_code) !== false) {
        $content = str_replace($old_code, $new_code, $content);
        $content = str_replace($old_if, $new_if, $content);
        file_put_contents($target_file, $content);
        echo "✔ Successfully patched category.php!<br/>";
    } else {
        echo "✔ category.php is already up to date.<br/>";
    }
} else {
    echo "❌ category.php not found at $target_file.<br/>";
}

echo "<h2>2. Updating Database (Filter Banner + Map Settings)...</h2>";

// 1. Disable Banner Module 82
$res = mysqli_query($link, "SELECT setting FROM {$prefix}module WHERE module_id = 82");
if ($row = mysqli_fetch_assoc($res)) {
    $setting = json_decode($row['setting'], true);
    $setting['status'] = "0";
    $new_setting = mysqli_real_escape_string($link, json_encode($setting));
    mysqli_query($link, "UPDATE {$prefix}module SET setting = '{$new_setting}' WHERE module_id = 82");
    echo "✔ Module 82 (Armchair Banner) set to disabled.<br/>";
}
mysqli_query($link, "DELETE FROM {$prefix}layout_module WHERE code = 'so_theme.so_html_content.82'");

// 2. Update Map Geocode & Address in oc_setting
mysqli_query($link, "UPDATE {$prefix}setting SET `value` = '27.7017,85.3206' WHERE `key` = 'config_geocode'");
echo "✔ Updated store geocode to 27.7017,85.3206.<br/>";

// 3. Update mapaddress in oc_soconfig
$res_so = mysqli_query($link, "SELECT id, `value` FROM {$prefix}soconfig WHERE `key` = 'soconfig_general_store'");
while ($so_row = mysqli_fetch_assoc($res_so)) {
    $so_val = json_decode($so_row['value'], true);
    if (is_array($so_val)) {
        $so_val['mapaddress'] = 'Kathmandu, Nepal';
        $so_val['mapgeocode'] = '27.7017,85.3206';
        $new_so_val = mysqli_real_escape_string($link, json_encode($so_val));
        mysqli_query($link, "UPDATE {$prefix}soconfig SET `value` = '{$new_so_val}' WHERE id = " . (int)$so_row['id']);
    }
}
echo "✔ Updated soconfig map address to Kathmandu, Nepal.<br/>";

echo "<h2>3. Clearing Storage Cache...</h2>";
$cache_dir = defined('DIR_STORAGE') ? DIR_STORAGE . 'cache/' : __DIR__ . '/system/storage/cache/';

function purge_cache($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), array('.', '..'));
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            purge_cache($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

if (is_dir($cache_dir)) {
    purge_cache($cache_dir);
    echo "✔ Template cache cleared successfully!<br/>";
}

echo "<h2>All done! Remote site updated.</h2>";
