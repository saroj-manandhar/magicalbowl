<?php
// Script to grant administrator permissions for shipping import routes

if (is_file('config.php')) {
    require_once('config.php');
} else {
    die("Error: config.php not found.\n");
}

$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if (!$link) {
    echo "Notice: Could not connect to database on " . DB_HOSTNAME . ":" . DB_PORT . " (MySQL server might not be running locally right now). The script can be run whenever the database server is running.\n";
    exit(0);
}

echo "Connected to database " . DB_DATABASE . "\n";

$routes_to_add = [
    'extension/opencart/other/arameximport',
    'extension/opencart/other/aramexratesimport',
    'extension/opencart/other/fedexratesimport'
];

$res = mysqli_query($link, "SELECT user_group_id, permission FROM `" . DB_PREFIX . "user_group` WHERE user_group_id = 1");
if ($row = mysqli_fetch_assoc($res)) {
    $permissions = json_decode($row['permission'], true) ?: ['access' => [], 'modify' => []];

    if (!isset($permissions['access'])) $permissions['access'] = [];
    if (!isset($permissions['modify'])) $permissions['modify'] = [];

    $updated = false;
    foreach ($routes_to_add as $route) {
        if (!in_array($route, $permissions['access'])) {
            $permissions['access'][] = $route;
            $updated = true;
        }
        if (!in_array($route, $permissions['modify'])) {
            $permissions['modify'][] = $route;
            $updated = true;
        }
    }

    if ($updated) {
        $encoded = mysqli_real_escape_string($link, json_encode($permissions));
        mysqli_query($link, "UPDATE `" . DB_PREFIX . "user_group` SET permission = '$encoded' WHERE user_group_id = 1");
        echo "✔ Administrator permissions updated for shipping import routes.\n";
    } else {
        echo "✔ Administrator already has permissions for all shipping import routes.\n";
    }
}

mysqli_close($link);
