<?php
$new_db = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($new_db->connect_error) {
    die("Connection failed: " . $new_db->connect_error);
}

echo "=== FILTER MODULES IN oc_module ===\n";
$res = $new_db->query("SELECT * FROM oc_module WHERE code LIKE '%filter%' OR name LIKE '%filter%'");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['module_id'] . " | Name: " . $row['name'] . " | Code: " . $row['code'] . "\n";
}

echo "\n=== LAYOUT ASSIGNMENTS FOR FILTER ===\n";
$res = $new_db->query("SELECT lm.*, l.name AS layout_name FROM oc_layout_module lm LEFT JOIN oc_layout l ON lm.layout_id = l.layout_id WHERE lm.code LIKE '%filter%'");
while ($row = $res->fetch_assoc()) {
    echo "Layout: " . $row['layout_name'] . " (ID: " . $row['layout_id'] . ") | Code: " . $row['code'] . " | Position: " . $row['position'] . " | Sort: " . $row['sort_order'] . "\n";
}

$new_db->close();
?>
