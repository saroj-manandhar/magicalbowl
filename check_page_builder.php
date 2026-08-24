<?php
$mysqli = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($mysqli->connect_error) {
    die("Connect Error: " . $mysqli->connect_error);
}

echo "=== OC_MODULE FOR ID 33 ===\n";
$result = $mysqli->query("SELECT * FROM oc_module WHERE module_id = 33");
if ($row = $result->fetch_assoc()) {
    echo "Module ID: {$row['module_id']} | Name: {$row['name']} | Code: {$row['code']}\n";
    $val = $row['setting'];
    $data = json_decode($val, true);
    if ($data === null) {
        $data = @unserialize($val);
    }
    if (is_array($data)) {
        echo "  Value: Array of " . count($data) . " items\n";
        print_r($data);
    } else {
        echo "  Raw Value: " . substr($val, 0, 500) . "\n";
    }
} else {
    echo "Module 33 not found!\n";
}
