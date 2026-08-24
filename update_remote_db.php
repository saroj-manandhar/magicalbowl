<?php
// bootstrap opencart config
if (file_exists('config.php')) {
    require_once('config.php');
} else {
    die("config.php not found!");
}

// connect to DB
$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if (!$link) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "<h3>1. Updating Database (oc_soconfig)...</h3>";
$res = mysqli_query($link, "SELECT * FROM " . DB_PREFIX . "soconfig WHERE `key` = 'soconfig_general_store'");
if (!$res) {
    die("Error querying soconfig: " . mysqli_error($link));
}

$updated_count = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $config = json_decode($row['value'], true);
    if (is_array($config)) {
        $old_copyright = $config['copyright'] ?? '';
        $config['copyright'] = 'Magical Singing Bowls &copy; {year}. All Rights Reserved.';
        $new_value = json_encode($config);
        
        $stmt = mysqli_prepare($link, "UPDATE " . DB_PREFIX . "soconfig SET `value` = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $new_value, $row['id']);
        mysqli_stmt_execute($stmt);
        
        echo "Updated Store ID: " . $row['store_id'] . " (Old copyright was: " . htmlspecialchars($old_copyright) . ")<br/>";
        $updated_count++;
    }
}
echo "Total updated layouts: $updated_count<br/>";

echo "<h3>2. Clearing Twig Cache...</h3>";
$cache_dir = DIR_STORAGE . 'cache/template/';
if (is_dir($cache_dir)) {
    function delete_dir_files($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? delete_dir_files("$dir/$file") : unlink("$dir/$file");
        }
        return @rmdir($dir);
    }
    // Delete all subdirectories
    $subdirs = array_diff(scandir($cache_dir), array('.','..'));
    foreach ($subdirs as $subdir) {
        if (is_dir($cache_dir . $subdir)) {
            delete_dir_files($cache_dir . $subdir);
        }
    }
    echo "Twig cache cleared successfully!<br/>";
} else {
    echo "Cache directory $cache_dir not found.<br/>";
}

echo "<h3>3. Self-deleting this script...</h3>";
if (@unlink(__FILE__)) {
    echo "Script self-deleted for security.<br/>";
} else {
    echo "Please delete update_remote_db.php from your server manually.<br/>";
}
echo "<h3>Done! Please refresh your homepage.</h3>";
