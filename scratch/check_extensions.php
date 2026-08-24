<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$new_db = new mysqli('127.0.0.1', 'root', 'root', 'neww_msb', 8889);
if ($new_db->connect_error) {
    die("New DB Connection failed: " . $new_db->connect_error);
}

echo "=== INSTALLED EXTENSIONS ===\n";
$res_ext = $new_db->query("SELECT * FROM oc_extension WHERE type = 'module'");
if ($res_ext) {
    while ($row = $res_ext->fetch_assoc()) {
        echo "Code: " . $row['code'] . " | Extension: " . $row['extension'] . "\n";
    }
}

echo "\n=== ALL INSTALLED MODULES ===\n";
$res_mod = $new_db->query("SELECT * FROM oc_module");
if ($res_mod) {
    while ($row = $res_mod->fetch_assoc()) {
        echo "ID: " . $row['module_id'] . " | Name: " . $row['name'] . " | Code: " . $row['code'] . "\n";
    }
}

$new_db->close();
?>
