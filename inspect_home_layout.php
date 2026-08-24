<?php
$mysqli = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($mysqli->connect_error) {
    die("Connect Error: " . $mysqli->connect_error);
}

// 1. Get Home layout ID
$layout_id = 0;
$result = $mysqli->query("SELECT l.layout_id, l.name FROM oc_layout_route lr LEFT JOIN oc_layout l ON lr.layout_id = l.layout_id WHERE lr.route = 'common/home'");
if ($result && $row = $result->fetch_assoc()) {
    $layout_id = $row['layout_id'];
    echo "Home Layout Name: " . $row['name'] . " (ID: $layout_id)\n";
} else {
    echo "Home Layout not found in oc_layout_route!\n";
}

if ($layout_id > 0) {
    echo "\n--- Modules on Home Layout (ID: $layout_id) ---\n";
    $result = $mysqli->query("SELECT lm.*, m.name as module_name FROM oc_layout_module lm LEFT JOIN oc_module m ON lm.code = CONCAT('module.', m.code) OR lm.code LIKE CONCAT('%', m.module_id) OR lm.code = m.code WHERE lm.layout_id = $layout_id ORDER BY lm.position, lm.sort_order");
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
}
