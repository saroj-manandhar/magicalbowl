<?php
define('APPLICATION', 'Admin');
require_once __DIR__ . '/config.php';
require_once DIR_SYSTEM . 'startup.php';

// Autoloader
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\Admin', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

try {
    $db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

    // 1. Update soconfig_general_store
    $query = $db->query("SELECT * FROM " . DB_PREFIX . "soconfig WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
    if ($query->num_rows > 0) {
        $row = $query->row;
        $val = json_decode($row['value'], true);
        $val['themecolor'] = 'orange';
        $new_val = json_encode($val, JSON_UNESCAPED_SLASHES);
        $db->query("UPDATE " . DB_PREFIX . "soconfig SET `value` = '" . $db->escape($new_val) . "' WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
        echo "<h3>Success: General store color updated to orange!</h3>";
    }

    // 2. Update soconfig_advanced_store
    $query = $db->query("SELECT * FROM " . DB_PREFIX . "soconfig WHERE store_id = 0 AND `key` = 'soconfig_advanced_store'");
    if ($query->num_rows > 0) {
        $row = $query->row;
        $val = json_decode($row['value'], true);
        $val['name_color'] = 'orange';
        $val['theme_color'] = '#fe5722';
        $new_val = json_encode($val, JSON_UNESCAPED_SLASHES);
        $db->query("UPDATE " . DB_PREFIX . "soconfig SET `value` = '" . $db->escape($new_val) . "' WHERE store_id = 0 AND `key` = 'soconfig_advanced_store'");
        echo "<h3>Success: Advanced store color updated to orange!</h3>";
    }

    // 3. Clear minify cache files
    $minify_dir = DIR_EXTENSION . 'so_theme/catalog/view/theme/minify/';
    if (is_dir($minify_dir)) {
        $files = glob($minify_dir . '*.css');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "<h3>Success: Minified CSS theme cache cleared!</h3>";
    }

    echo "<h2 style='color: green;'>Theme color successfully changed to Orange! Please delete this file now.</h2>";

} catch (\Exception $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
}
