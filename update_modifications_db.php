<?php
// Script to update permissions and seed oc_modification table for OpenCart 4

if (is_file('config.php')) {
    require_once('config.php');
} else {
    die("Error: config.php not found. Please run this script in the root directory of your OpenCart installation.\n");
}

$link = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if (!$link) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

echo "Connected to database " . DB_DATABASE . " on " . DB_HOSTNAME . ":" . DB_PORT . "\n";

// 1. Ensure oc_modification table exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "modification` (
  `modification_id` int(11) NOT NULL AUTO_INCREMENT,
  `extension_install_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `code` varchar(64) NOT NULL,
  `author` varchar(64) NOT NULL,
  `version` varchar(32) NOT NULL,
  `link` varchar(255) NOT NULL,
  `xml` mediumtext NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`modification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($link, $create_table_sql)) {
    echo "Table oc_modification is ready.\n";
} else {
    echo "Error creating table: " . mysqli_error($link) . "\n";
}

// 2. Grant permissions to Administrator user group (user_group_id = 1)
$res = mysqli_query($link, "SELECT permission FROM `" . DB_PREFIX . "user_group` WHERE user_group_id = 1");
if ($row = mysqli_fetch_assoc($res)) {
    $permissions = json_decode($row['permission'], true);
    
    $routes_to_add = [
        'marketplace/modification',
        'extension/huntbee',
        'extension/huntbee/module/base_plugin',
        'extension/huntbee/module/hb_cart',
        'extension/huntbee/module/order_review',
        'extension/tmd',
        'extension/tmd/other/import',
        'extension/tmd/other/export'
    ];
    
    foreach ($routes_to_add as $route) {
        if (!in_array($route, $permissions['access'])) {
            $permissions['access'][] = $route;
        }
        if (!in_array($route, $permissions['modify'])) {
            $permissions['modify'][] = $route;
        }
    }
    
    $updated_permission_json = mysqli_real_escape_string($link, json_encode($permissions));
    mysqli_query($link, "UPDATE `" . DB_PREFIX . "user_group` SET permission = '$updated_permission_json' WHERE user_group_id = 1");
    echo "Updated administrator user group permissions.\n";
}

// 3. Seed modifications into oc_modification
$modifications = [
    [
        'name' => 'Abandoned Cart - MarketinSG Quick Checkout Patch',
        'code' => 'hb_cart_patch',
        'author' => 'HuntBee OpenCart Services',
        'version' => '1.0.0',
        'date_added' => '2023-05-18 10:00:00',
        'description' => 'Patch for MarketinSG Quick Checkout compatibility with Abandoned Cart Email',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_cart_patch</code><name>Abandoned Cart - MarketinSG Quick Checkout Patch</name><version>1.0.0</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'Abandoned Cart Email (3xxx)',
        'code' => 'hb_cart',
        'author' => 'HuntBee OpenCart Services',
        'version' => '3.1.5',
        'date_added' => '2025-04-28 10:00:00',
        'description' => 'Automated & Manual Abandoned Cart Email Notifications and Popup',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_cart</code><name>Abandoned Cart Email (3xxx)</name><version>3.1.5</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'Base Plugin from HuntBee (3xxx)',
        'code' => 'hb_base',
        'author' => 'HuntBee OpenCart Services',
        'version' => '3.0.0',
        'date_added' => '2023-05-16 10:00:00',
        'description' => 'Base Core Plugin for HuntBee Extensions',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_base</code><name>Base Plugin from HuntBee (3xxx)</name><version>3.0.0</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'FeedbackFlow: Post-Purchase Review Invitation',
        'code' => 'hb_order_review',
        'author' => 'HuntBee OpenCart Services',
        'version' => '3.2.3',
        'date_added' => '2025-04-28 10:00:00',
        'description' => 'FeedbackFlow Post-Purchase Customer Review Invitations',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_order_review</code><name>FeedbackFlow: Post-Purchase Review Invitation</name><version>3.2.3</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'TMD Import Export Module',
        'code' => 'tmd_import_export',
        'author' => 'TMD(opencartextensions.in)',
        'version' => '3.x',
        'date_added' => '2018-11-05 10:00:00',
        'description' => 'TMD Excel Import Export Module for OpenCart',
        'link' => 'http://opencartextensions.in/',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>tmd_import_export</code><name>TMD Import Export Module</name><version>3.x</version><author>TMD(opencartextensions.in)</author></modification>'
    ]
];

foreach ($modifications as $mod) {
    $name = mysqli_real_escape_string($link, $mod['name']);
    $code = mysqli_real_escape_string($link, $mod['code']);
    $author = mysqli_real_escape_string($link, $mod['author']);
    $version = mysqli_real_escape_string($link, $mod['version']);
    $date_added = mysqli_real_escape_string($link, $mod['date_added']);
    $description = mysqli_real_escape_string($link, $mod['description']);
    $link_url = mysqli_real_escape_string($link, $mod['link']);
    $xml = mysqli_real_escape_string($link, $mod['xml']);

    // Check if exists by code or name
    $check = mysqli_query($link, "SELECT modification_id FROM `" . DB_PREFIX . "modification` WHERE code = '$code' OR name = '$name'");
    if (mysqli_num_rows($check) == 0) {
        $insert_sql = "INSERT INTO `" . DB_PREFIX . "modification` (`extension_install_id`, `name`, `description`, `code`, `author`, `version`, `link`, `xml`, `status`, `date_added`) 
                       VALUES (0, '$name', '$description', '$code', '$author', '$version', '$link_url', '$xml', 1, '$date_added')";
        if (mysqli_query($link, $insert_sql)) {
            echo "Added modification: {$mod['name']}\n";
        } else {
            echo "Error inserting modification {$mod['name']}: " . mysqli_error($link) . "\n";
        }
    } else {
        echo "Modification already exists: {$mod['name']}\n";
    }
}

echo "Modification setup complete.\n";
