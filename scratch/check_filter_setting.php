<?php
$new_db = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($new_db->connect_error) {
    die("Connection failed: " . $new_db->connect_error);
}

$res = $new_db->query("SELECT * FROM oc_module WHERE module_id = 85");
if ($row = $res->fetch_assoc()) {
    echo "Module Name: " . $row['name'] . "\n";
    $setting = json_decode($row['setting'], true);
    file_put_contents("/Users/sarojmanandhar/Sites/localhost/neww_magicalsingingbowls/scratch/filter_setting_85.json", json_encode($setting, JSON_PRETTY_PRINT));
    echo "Wrote setting of module 85 to filter_setting_85.json\n";
} else {
    echo "Module 85 not found.\n";
}

$new_db->close();
?>
