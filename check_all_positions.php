<?php
$mysqli = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($mysqli->connect_error) {
    die("Connect Error: " . $mysqli->connect_error);
}

echo "=== ALL MODULES ON HOME LAYOUT ===\n";
$sql = "SELECT lm.*, m.name as module_name 
        FROM oc_layout_module lm 
        LEFT JOIN oc_module m ON lm.code = CONCAT('module.', m.code) OR lm.code LIKE CONCAT('%', m.module_id) OR lm.code = m.code 
        WHERE lm.layout_id = 1 
        ORDER BY lm.position, lm.sort_order";

$result = $mysqli->query($sql);
$positions = [];
while ($row = $result->fetch_assoc()) {
    $positions[$row['position']][] = [
        "sort" => $row['sort_order'],
        "code" => $row['code'],
        "name" => $row['module_name']
    ];
}

foreach ($positions as $pos => $mods) {
    echo "Position: $pos\n";
    foreach ($mods as $m) {
        echo "  - Sort: {$m['sort']} | Code: {$m['code']} | Name: {$m['name']}\n";
    }
}
