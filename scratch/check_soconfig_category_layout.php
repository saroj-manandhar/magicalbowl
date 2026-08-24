<?php
$new_db = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($new_db->connect_error) {
    die("Connection failed: " . $new_db->connect_error);
}

$res = $new_db->query("SELECT * FROM oc_soconfig");
while ($row = $res->fetch_assoc()) {
    $decoded = json_decode($row['value'], true);
    if ($decoded) {
        foreach ($decoded as $k => $v) {
            if (strpos($k, 'category') !== false || strpos($k, 'sidebar') !== false || strpos($k, 'column') !== false || strpos($k, 'layout') !== false) {
                echo "Key: " . $row['key'] . " | Config Key: $k | Value: " . json_encode($v) . "\n";
            }
        }
    }
}
$new_db->close();
?>
