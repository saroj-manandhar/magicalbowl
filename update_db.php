<?php
// Database Update Helper Tool

if (is_file('config.php')) {
    require_once('config.php');
} else {
    die("Error: config.php not found. Please run this script in the root directory of your website.\n");
}

echo "Connecting to database " . DB_DATABASE . " on " . DB_HOSTNAME . ":" . DB_PORT . "...\n";

$link = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if (!$link) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

echo "Connected successfully.\n";

// Helper to check if a column exists
function column_exists($link, $table, $column) {
    $q = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($q) > 0;
}

// 1. Add columns if missing
if (!column_exists($link, 'oc_product_description', 'diameter')) {
    echo "Adding 'diameter' column to oc_product_description...\n";
    mysqli_query($link, "ALTER TABLE `oc_product_description` ADD `diameter` TEXT NULL");
} else {
    echo "'diameter' column already exists.\n";
}

if (!column_exists($link, 'oc_product', 'sound_embed')) {
    echo "Adding 'sound_embed' column to oc_product...\n";
    mysqli_query($link, "ALTER TABLE `oc_product` ADD `sound_embed` TEXT NULL");
} else {
    echo "'sound_embed' column already exists.\n";
}

if (!column_exists($link, 'oc_product', 'viewed')) {
    echo "Adding 'viewed' column to oc_product...\n";
    mysqli_query($link, "ALTER TABLE `oc_product` ADD `viewed` INT(11) NOT NULL DEFAULT 0");
} else {
    echo "'viewed' column already exists.\n";
}

// 2. Load and run update queries from update_db.sql
$sql_file = 'update_db.sql';
if (!is_file($sql_file)) {
    die("Error: $sql_file not found. Please make sure $sql_file is in the same directory as this script.\n");
}

echo "Executing data updates from $sql_file...\n";

$lines = file($sql_file);
$query = '';
$count = 0;
$errors = 0;

foreach ($lines as $line) {
    // Skip comments and empty lines
    if (substr(trim($line), 0, 2) == '--' || trim($line) == '') {
        continue;
    }
    
    // Skip ALTER TABLE lines since we already handled them safely
    if (stripos($line, 'ALTER TABLE') !== false) {
        continue;
    }

    $query .= $line;
    
    // If it has a semicolon at the end, it's the end of the query
    if (substr(trim($line), -1, 1) == ';') {
        if (mysqli_query($link, $query)) {
            $count++;
        } else {
            $errors++;
            // echo "Error running query: " . mysqli_error($link) . "\n";
        }
        $query = '';
    }
}

echo "\nFinished executing updates.\n";
echo "Successfully executed queries: $count\n";
if ($errors > 0) {
    echo "Failed queries: $errors (usually due to rows not existing in the new database yet)\n";
}
