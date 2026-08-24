<?php
$mysqli = new mysqli("127.0.0.1", "root", "root", "neww_msb", 8889);
if ($mysqli->connect_error) {
    die("Connect failed: " . $mysqli->connect_error);
}
$result = $mysqli->query("SELECT order_id, firstname, lastname, email, total, currency_code, date_added, order_status_id FROM oc_order ORDER BY order_id DESC LIMIT 10");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
$mysqli->close();
