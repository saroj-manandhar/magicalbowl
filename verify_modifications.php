<?php
// Script to verify modifications in OpenCart 4 database

if (is_file('config.php')) {
    require_once('config.php');
} else {
    die("Error: config.php not found. Please run this script in the root directory of your OpenCart installation.\n");
}

$link = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if (!$link) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

echo "Connected to database " . DB_DATABASE . " on " . DB_HOSTNAME . ":" . DB_PORT . "\n\n";

$res = mysqli_query($link, "SELECT modification_id, name, author, version, status, date_added FROM `" . DB_PREFIX . "modification` ORDER BY modification_id ASC");

if (!$res) {
    die("Error querying oc_modification table: " . mysqli_error($link) . "\n");
}

echo "Modifications in database (`" . DB_PREFIX . "modification`):\n";
echo str_repeat("-", 100) . "\n";
printf("%-4s | %-50s | %-12s | %-8s | %-8s\n", "ID", "Name", "Author", "Version", "Status");
echo str_repeat("-", 100) . "\n";

while ($row = mysqli_fetch_assoc($res)) {
    $status_str = $row['status'] ? 'Enabled' : 'Disabled';
    printf("%-4d | %-50s | %-12s | %-8s | %-8s\n", 
        $row['modification_id'], 
        substr($row['name'], 0, 50), 
        substr($row['author'], 0, 12), 
        $row['version'], 
        $status_str
    );
}
echo str_repeat("-", 100) . "\n";
