<?php
header('Content-Type: text/plain');
if (file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
} else {
    die('config.php not found');
}
$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if (!$link) {
    die('DB error: ' . mysqli_connect_error());
}
echo 'DB connected!
';

if (isset($_GET['action']) && $_GET['action'] == 'update_ms010') {
    $specs = "Name: Complete Musical Set Tibetan/Himalayan Singing Bowl<br>
Size: Medium Set<br>
Weight: 4100 gm (app)<br>
Diameter: 18 cm (app)<br>
Height: 9.5 cm (app)<br>
Type: Handmade, Jambati<br>
Color: Maja<br>
Design: Plain<br><br>
<div align="justify"><b>Every instrument is individually tested in our acoustics Sound laboratory and accompanied by a serial-matched Lab Report Certificate that details its exact fundamental frequency (f0), harmonic overtones (f1), musical note and cent deviation, visual acoustic spectrum, precise striking point, and verified Therapeutic recommendations</b></div>";
    $specs_esc = mysqli_real_escape_string($link, $specs);
    $res = mysqli_query($link, "UPDATE " . DB_PREFIX . "product_description SET diameter = '{$specs_esc}' WHERE product_id = 4683");
    echo 'Update result: ' . ($res ? 'SUCCESS' : 'FAILED: ' . mysqli_error($link)) . '
';
}

$res = mysqli_query($link, "SELECT p.product_id, p.master_id, p.model, p.override, pd.diameter FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id WHERE p.product_id = 4683");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
