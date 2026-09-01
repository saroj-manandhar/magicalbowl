<?php
define('APPLICATION', 'Admin');
require_once __DIR__ . '/config.php';
require_once DIR_SYSTEM . 'startup.php';

// Autoloader
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\Admin', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

try {
    $db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

    // 1. Update soconfig_general_store database settings
    $query = $db->query("SELECT * FROM " . DB_PREFIX . "soconfig WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
    if ($query->num_rows > 0) {
        $row = $query->row;
        $val = json_decode($row['value'], true);
        $val['typeheader'] = '1';
        $val['typefooter'] = '2';
        $val['themecolor'] = 'red';
        $new_val = json_encode($val, JSON_UNESCAPED_SLASHES);
        $db->query("UPDATE " . DB_PREFIX . "soconfig SET `value` = '" . $db->escape($new_val) . "' WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
        echo "<h3>Success: General store fixed (typeheader=1, typefooter=2, themecolor=red)!</h3>";
    }

    // 2. Update soconfig_advanced_store database settings
    $query2 = $db->query("SELECT * FROM " . DB_PREFIX . "soconfig WHERE store_id = 0 AND `key` = 'soconfig_advanced_store'");
    if ($query2->num_rows > 0) {
        $row2 = $query2->row;
        $val2 = json_decode($row2['value'], true);
        $val2['name_color'] = 'red';
        $val2['theme_color'] = '#d96b00';
        $new_val2 = json_encode($val2, JSON_UNESCAPED_SLASHES);
        $db->query("UPDATE " . DB_PREFIX . "soconfig SET `value` = '" . $db->escape($new_val2) . "' WHERE store_id = 0 AND `key` = 'soconfig_advanced_store'");
        echo "<h3>Success: Advanced store updated!</h3>";
    }

    // 3. Populate 0-byte layout1 CSS files
    $layout1_dir = DIR_EXTENSION . 'so_theme/catalog/view/template/css/layout1/';
    $red_css = $layout1_dir . 'red.css';
    if (file_exists($red_css)) {
        $css_content = file_get_contents($red_css);
        if (strlen($css_content) > 1000) {
            $files = glob($layout1_dir . '*.css');
            foreach ($files as $file) {
                if (filesize($file) === 0) {
                    file_put_contents($file, $css_content);
                    echo "<h3>Success: Populated " . basename($file) . " with red.css (" . strlen($css_content) . " bytes)!</h3>";
                }
            }
        }
    }

    // 4. Patch soconfig.twig
    $soconfig_twig = DIR_EXTENSION . 'so_theme/admin/view/template/soconfig/soconfig.twig';
    if (file_exists($soconfig_twig)) {
        $t_content = file_get_contents($soconfig_twig);
        $pattern = '/\$keylayout\s*=\s*\$\(this\)\.data\("keylayout"\);\s*\$keyheader\s*=\s*\$\(this\)\.data\("keyheader"\);.*?\#tab-general__footertype.*?\}\);\s*\}/s';
        $replace = '$keylayout = $(this).data("keylayout"); $store_active = {{active_store}}; /* Header & Footer fixed */ }';
        if (preg_match($pattern, $t_content)) {
            $t_content = preg_replace($pattern, $replace, $t_content);
            file_put_contents($soconfig_twig, $t_content);
            echo "<h3>Success: Patched soconfig.twig (decoupled header/footer)!</h3>";
        }
    }

    // 5. Patch admin soconfig.php
    $admin_soconfig = DIR_EXTENSION . 'so_theme/admin/controller/module/soconfig.php';
    if (file_exists($admin_soconfig)) {
        $a_content = file_get_contents($admin_soconfig);
        if (strpos($a_content, "['typeheader'] = '1'") === false) {
            $a_target = "if (  \$this->request->server['REQUEST_METHOD'] == 'POST' && \$this->validate() ) {";
            $a_replace = "if (  \$this->request->server['REQUEST_METHOD'] == 'POST' && \$this->validate() ) {\n\t\t\tif (isset(\$this->request->post['soconfig_general_store'])) {\n\t\t\t\t\$this->request->post['soconfig_general_store']['typeheader'] = '1';\n\t\t\t\t\$this->request->post['soconfig_general_store']['typefooter'] = '2';\n\t\t\t}";
            $a_content = str_replace($a_target, $a_replace, $a_content);
            file_put_contents($admin_soconfig, $a_content);
            echo "<h3>Success: Patched admin soconfig.php!</h3>";
        }
    }

    // 6. Patch class/soconfig.php
    $class_soconfig = DIR_EXTENSION . 'so_theme/admin/view/template/soconfig/class/soconfig.php';
    if (file_exists($class_soconfig)) {
        $c_content = file_get_contents($class_soconfig);
        $c_content = str_replace("\$themeCssHeader   \t= 'header/header'.\$typeheader.'.css';", "\$themeCssHeader   \t= 'header/header1.css';", $c_content);
        $c_content = str_replace("\$themeCssHeaderRTL  = 'header/header'.\$typeheader.'-rtl.css';", "\$themeCssHeaderRTL  = 'header/header1-rtl.css';", $c_content);
        $c_content = str_replace("\$themeCssFooter   \t= 'footer/footer'.\$typefooter.'.css';", "\$themeCssFooter   \t= 'footer/footer2.css';", $c_content);
        $c_content = str_replace("\$themeCssFooterRTL  = 'footer/footer'.\$typefooter.'-rtl.css';", "\$themeCssFooterRTL  = 'footer/footer2-rtl.css';", $c_content);
        
        if (strpos($c_content, 'Robust Fallback: Prevent empty') === false) {
            $c_target_fb = "\$themeCssNameRTL = 'theme.css';\n\t\tendif;";
            $c_replace_fb = "\$themeCssNameRTL = 'theme.css';\n\t\tendif;\n\n\t\t// Robust Fallback: Prevent empty or missing CSS files from breaking the layout\n\t\t\$cssFullPath = DIR_EXTENSION . 'so_theme/catalog/view/template/css/' . \$themeCssName;\n\t\tif (!file_exists(\$cssFullPath) || filesize(\$cssFullPath) === 0) {\n\t\t\t\$foundFallback = false;\n\t\t\t\$layoutDir = DIR_EXTENSION . 'so_theme/catalog/view/template/css/layout' . \$typelayout;\n\t\t\tif (is_dir(\$layoutDir)) {\n\t\t\t\tforeach (scandir(\$layoutDir) as \$f) {\n\t\t\t\t\tif (substr(\$f, -4) === '.css' && strpos(\$f, '-rtl') === false && filesize(\$layoutDir . '/' . \$f) > 0) {\n\t\t\t\t\t\t\$themeCssName = 'layout' . \$typelayout . '/' . \$f;\n\t\t\t\t\t\t\$foundFallback = true;\n\t\t\t\t\t\tbreak;\n\t\t\t\t\t}\n\t\t\t\t}\n\t\t\t}\n\t\t\tif (!\$foundFallback) {\n\t\t\t\t\$themeCssName = 'layout1/red.css';\n\t\t\t}\n\t\t}\n\t\t\$cssFullPathRTL = DIR_EXTENSION . 'so_theme/catalog/view/template/css/' . \$themeCssNameRTL;\n\t\tif (!file_exists(\$cssFullPathRTL) || filesize(\$cssFullPathRTL) === 0) {\n\t\t\t\$themeCssNameRTL = \$themeCssName;\n\t\t}";
            $c_content = str_replace($c_target_fb, $c_replace_fb, $c_content);
        }
        
        $c_content = str_replace(
            "if (strpos(\$value, '-rtl') == false && strpos(\$value, '.css') == true)",
            "if (strpos(\$value, '-rtl') === false && strpos(\$value, '.css') !== false && filesize(\$log_directory . '/' . \$value) > 0)",
            $c_content
        );
        file_put_contents($class_soconfig, $c_content);
        echo "<h3>Success: Patched class/soconfig.php (0-byte CSS fallback added)!</h3>";
    }

    // 7. Patch header.twig & footer.twig
    $header_twig = DIR_EXTENSION . 'so_theme/catalog/view/template/common/header.twig';
    if (file_exists($header_twig)) {
        $h_content = file_get_contents($header_twig);
        if (strpos($h_content, 'Fixed Header 1 for Magical Singing Bowls') === false) {
            $h_pattern = '/\{\#\s*========\s*Show Header.*?\{\% endif \%\}/s';
            $h_replace = "{# =========== Fixed Header 1 for Magical Singing Bowls============== #}\n{% include theme_directory~'/view/template/header/header1.twig' with {typeheader: '1'} %}";
            if (preg_match($h_pattern, $h_content)) {
                $h_content = preg_replace($h_pattern, $h_replace, $h_content);
                file_put_contents($header_twig, $h_content);
                echo "<h3>Success: Patched header.twig!</h3>";
            }
        }
    }

    $footer_twig = DIR_EXTENSION . 'so_theme/catalog/view/template/common/footer.twig';
    if (file_exists($footer_twig)) {
        $f_content = file_get_contents($footer_twig);
        if (strpos($f_content, 'Fixed Footer 2 for Magical Singing Bowls') === false) {
            $f_pattern = '/\{\#\s*========\s*Show Header.*?\{\% endif \%\}/s';
            $f_replace = "{# =========== Fixed Footer 2 for Magical Singing Bowls==============#}\n{% include theme_directory~'/view/template/footer/footer2.twig' with {typefooter: '2'} %}";
            if (preg_match($f_pattern, $f_content)) {
                $f_content = preg_replace($f_pattern, $f_replace, $f_content);
                file_put_contents($footer_twig, $f_content);
                echo "<h3>Success: Patched footer.twig!</h3>";
            }
        }
    }

    // 8. Patch event so_soconfig.php
    $so_soconfig_file = DIR_EXTENSION . 'so_theme/catalog/controller/event/so_soconfig.php';
    if (file_exists($so_soconfig_file)) {
        $so_c = file_get_contents($so_soconfig_file);
        if (strpos($so_c, 'footer_block2') !== false && strpos($so_c, '// add position - Footer 2 is fixed') === false) {
            $so_c = str_replace(
                "if( \$this->soconfig->get_settings('typefooter') == 1) \$data['footer_block1'] = \$this->load->controller('extension/so_theme/soconfig/footer_block_one');\n\t\telse if( \$this->soconfig->get_settings('typefooter') == 2) \$data['footer_block2'] = \$this->load->controller('extension/so_theme/soconfig/footer_block_two');",
                "// add position - Footer 2 is fixed for Magical Singing Bowls\n\t\t\$data['footer_block2'] = \$this->load->controller('extension/so_theme/soconfig/footer_block_two');",
                $so_c
            );
            file_put_contents($so_soconfig_file, $so_c);
            echo "<h3>Success: Patched so_soconfig.php event controller!</h3>";
        }
    }

    // 9. Clear minified theme cache
    $minify_dir = DIR_EXTENSION . 'so_theme/catalog/view/theme/minify/';
    if (is_dir($minify_dir)) {
        $files = glob($minify_dir . '*.css');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "<h3>Success: Minified CSS theme cache cleared!</h3>";
    }

    echo "<h2 style='color: green;'>Theme layout decoupling & Header/Footer locking successfully executed live!</h2>";

} catch (\Exception $e) {
    echo "<h3 style='color: red;'>Error: " . $e->getMessage() . "</h3>";
}

