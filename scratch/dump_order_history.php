<?php
$mysqli = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($mysqli->connect_error) {
    die("Connect failed: " . $mysqli->connect_error);
}
$result = $mysqli->query("SELECT * FROM oc_order_history WHERE order_id = 712 ORDER BY order_history_id DESC");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
$mysqli->close();
