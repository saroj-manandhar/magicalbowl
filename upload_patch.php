<?php
// Remote patch script for catalog/controller/product/category.php, database, map, and cache

if (file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
} else {
    die("config.php not found!");
}

$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$prefix = DB_PREFIX;

if (!$link) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "<h2>1. Patching category.php File...</h2>";
$target_file = __DIR__ . '/catalog/controller/product/category.php';

if (file_exists($target_file)) {
    $content = file_get_contents($target_file);
    $old_code = '$custom_filter_categories = [20, 27, 150];';
    $new_code = '$custom_filter_categories = [27];';
    $old_if = 'if (in_array($category_id, $custom_filter_categories) || in_array($category_info[\'parent_id\'], $custom_filter_categories)) {';
    $new_if = 'if (in_array($category_id, $custom_filter_categories) || in_array($category_info[\'parent_id\'], $custom_filter_categories) || in_array(27, $parts)) {';

    if (strpos($content, $old_code) !== false) {
        $content = str_replace($old_code, $new_code, $content);
        $content = str_replace($old_if, $new_if, $content);
        file_put_contents($target_file, $content);
        echo "✔ Successfully patched category.php!<br/>";
    } else {
        echo "✔ category.php is already up to date.<br/>";
    }
} else {
    echo "❌ category.php not found at $target_file.<br/>";
}

echo "<h2>1.1. Patching so_soconfig.php File (Discount Percentage Calculation)...</h2>";
$so_target = __DIR__ . '/extension/so_theme/catalog/controller/event/so_soconfig.php';
if (file_exists($so_target)) {
    $so_content = file_get_contents($so_target);
    $old_discount_code = '$special = str_replace($results_currencies[\'symbol_left\'],\'\',$data[\'special\']);';
    if (strpos($so_content, $old_discount_code) !== false) {
        $old_block = '		$this->load->model(\'localisation/currency\');
		$results_currencies = $this->model_localisation_currency->getCurrencyByCode($this->session->data[\'currency\']);
		
		$special = str_replace($results_currencies[\'symbol_left\'],\'\',$data[\'special\']);
	    $special = str_replace($results_currencies[\'symbol_right\'],\'\',$special);
		$price = str_replace($results_currencies[\'symbol_left\'],\'\',$data[\'price\']);
	    $price = str_replace($results_currencies[\'symbol_right\'],\'\',$price);

		if ((float)$special) $data[\'discount\'] = \'-\'.round((((float)$price - (float)$special)/(float)$price)*100, 0).\'%\'; 
        else  $data[\'discount\'] = false;';

        $new_block = '		$this->load->model(\'catalog/product\');
		$product_info = $this->model_catalog_product->getProduct($data[\'product_id\']);

		if (isset($product_info[\'special\']) && (float)$product_info[\'special\'] > 0 && (float)$product_info[\'price\'] > 0) {
			$data[\'discount\'] = \'-\' . round((((float)$product_info[\'price\'] - (float)$product_info[\'special\']) / (float)$product_info[\'price\']) * 100, 0) . \'%\';
		} elseif (!empty($data[\'special\']) && !empty($data[\'price\'])) {
			$this->load->model(\'localisation/currency\');
			$results_currencies = $this->model_localisation_currency->getCurrencyByCode($this->session->data[\'currency\']);
			$thousand_point = $this->language->get(\'thousand_point\') ?: \',\';
			$decimal_point = $this->language->get(\'decimal_point\') ?: \'.\';

			$special = str_replace([$results_currencies[\'symbol_left\'] ?? \'\', $results_currencies[\'symbol_right\'] ?? \'\', $thousand_point, \' \'], \'\', $data[\'special\']);
			$special = str_replace($decimal_point, \'.\', $special);
			$price = str_replace([$results_currencies[\'symbol_left\'] ?? \'\', $results_currencies[\'symbol_right\'] ?? \'\', $thousand_point, \' \'], \'\', $data[\'price\']);
			$price = str_replace($decimal_point, \'.\', $price);

			if ((float)$price > 0 && (float)$special > 0) {
				$data[\'discount\'] = \'-\' . round((((float)$price - (float)$special) / (float)$price) * 100, 0) . \'%\';
			} else {
				$data[\'discount\'] = false;
			}
		} else {
			$data[\'discount\'] = false;
		}';
        $so_content = str_replace($old_block, $new_block, $so_content);
        $so_content = str_replace("\n        \$product_info = \$this->model_catalog_product->getProduct(\$data['product_id']);", '', $so_content);
        file_put_contents($so_target, $so_content);
        echo "✔ Successfully patched so_soconfig.php!<br/>";
    } else {
        echo "✔ so_soconfig.php is already up to date.<br/>";
    }
} else {
    echo "❌ so_soconfig.php not found at $so_target.<br/>";
}

echo "<h2>2. Updating Database (Filter Banner + Map Settings)...</h2>";

// 1. Disable Banner Module 82
$res = mysqli_query($link, "SELECT setting FROM {$prefix}module WHERE module_id = 82");
if ($row = mysqli_fetch_assoc($res)) {
    $setting = json_decode($row['setting'], true);
    $setting['status'] = "0";
    $new_setting = mysqli_real_escape_string($link, json_encode($setting));
    mysqli_query($link, "UPDATE {$prefix}module SET setting = '{$new_setting}' WHERE module_id = 82");
    echo "✔ Module 82 (Armchair Banner) set to disabled.<br/>";
}
mysqli_query($link, "DELETE FROM {$prefix}layout_module WHERE code = 'so_theme.so_html_content.82'");

// 2. Update Map Geocode & Address in oc_setting
mysqli_query($link, "UPDATE {$prefix}setting SET `value` = '27.7017,85.3206' WHERE `key` = 'config_geocode'");
echo "✔ Updated store geocode to 27.7017,85.3206.<br/>";

// 3. Update mapaddress in oc_soconfig
$res_so = mysqli_query($link, "SELECT id, `value` FROM {$prefix}soconfig WHERE `key` = 'soconfig_general_store'");
while ($so_row = mysqli_fetch_assoc($res_so)) {
    $so_val = json_decode($so_row['value'], true);
    if (is_array($so_val)) {
        $so_val['mapaddress'] = 'Kathmandu, Nepal';
        $so_val['mapgeocode'] = '27.7017,85.3206';
        $new_so_val = mysqli_real_escape_string($link, json_encode($so_val));
        mysqli_query($link, "UPDATE {$prefix}soconfig SET `value` = '{$new_so_val}' WHERE id = " . (int)$so_row['id']);
    }
}
echo "✔ Updated soconfig map address to Kathmandu, Nepal.<br/>";

echo "<h2>3. Clearing Storage Cache...</h2>";
$cache_dir = defined('DIR_STORAGE') ? DIR_STORAGE . 'cache/' : __DIR__ . '/system/storage/cache/';

function purge_cache($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), array('.', '..'));
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            purge_cache($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

if (is_dir($cache_dir)) {
    purge_cache($cache_dir);
    echo "✔ Template cache cleared successfully!<br/>";
}

echo "<h2>4. Applying Layout Decoupling & Header/Footer Locking...</h2>";

// 1. Update Database (soconfig settings)
$res_gen = mysqli_query($link, "SELECT id, `value` FROM {$prefix}soconfig WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
if ($gen_row = mysqli_fetch_assoc($res_gen)) {
    $gen_val = json_decode($gen_row['value'], true);
    $gen_val['typeheader'] = '1';
    $gen_val['typefooter'] = '2';
    if (!isset($gen_val['themecolor']) || $gen_val['themecolor'] == 'blue' || $gen_val['themecolor'] == 'orange') {
        $gen_val['themecolor'] = 'red';
    }
    $new_gen_val = mysqli_real_escape_string($link, json_encode($gen_val, JSON_UNESCAPED_SLASHES));
    mysqli_query($link, "UPDATE {$prefix}soconfig SET `value` = '{$new_gen_val}' WHERE id = " . (int)$gen_row['id']);
    echo "✔ Database soconfig_general_store updated (typeheader=1, typefooter=2, themecolor=red).<br/>";
}

$res_adv = mysqli_query($link, "SELECT id, `value` FROM {$prefix}soconfig WHERE store_id = 0 AND `key` = 'soconfig_advanced_store'");
if ($adv_row = mysqli_fetch_assoc($res_adv)) {
    $adv_val = json_decode($adv_row['value'], true);
    $adv_val['name_color'] = 'red';
    $adv_val['theme_color'] = '#d96b00';
    $new_adv_val = mysqli_real_escape_string($link, json_encode($adv_val, JSON_UNESCAPED_SLASHES));
    mysqli_query($link, "UPDATE {$prefix}soconfig SET `value` = '{$new_adv_val}' WHERE id = " . (int)$adv_row['id']);
    echo "✔ Database soconfig_advanced_store updated.<br/>";
}

// 2. Populate 0-byte layout1 CSS files
$layout1_dir = __DIR__ . '/extension/so_theme/catalog/view/template/css/layout1/';
$red_css_file = $layout1_dir . 'red.css';
if (file_exists($red_css_file)) {
    $red_css_content = file_get_contents($red_css_file);
    if (strlen($red_css_content) > 1000) {
        $css_files = glob($layout1_dir . '*.css');
        foreach ($css_files as $cf) {
            if (filesize($cf) === 0) {
                file_put_contents($cf, $red_css_content);
                echo "✔ Populated " . basename($cf) . " with valid CSS (" . strlen($red_css_content) . " bytes).<br/>";
            }
        }
    }
}

// 3. Patch soconfig.twig
$soconfig_twig_path = __DIR__ . '/extension/so_theme/admin/view/template/soconfig/soconfig.twig';
if (file_exists($soconfig_twig_path)) {
    $st_content = file_get_contents($soconfig_twig_path);
    $st_pattern = '/\$keylayout\s*=\s*\$\(this\)\.data\("keylayout"\);\s*\$keyheader\s*=\s*\$\(this\)\.data\("keyheader"\);.*?\#tab-general__footertype.*?\}\);\s*\}/s';
    $st_replace = '$keylayout = $(this).data("keylayout"); $store_active = {{active_store}}; /* Header & Footer fixed */ }';
    if (preg_match($st_pattern, $st_content)) {
        $st_content = preg_replace($st_pattern, $st_replace, $st_content);
        file_put_contents($soconfig_twig_path, $st_content);
        echo "✔ Patched soconfig.twig (decoupled layout selection from header/footer).<br/>";
    }
}

// 4. Patch admin soconfig.php
$admin_soconfig_path = __DIR__ . '/extension/so_theme/admin/controller/module/soconfig.php';
if (file_exists($admin_soconfig_path)) {
    $as_content = file_get_contents($admin_soconfig_path);
    if (strpos($as_content, "['typeheader'] = '1'") === false) {
        $as_target = "if (  \$this->request->server['REQUEST_METHOD'] == 'POST' && \$this->validate() ) {";
        $as_replace = "if (  \$this->request->server['REQUEST_METHOD'] == 'POST' && \$this->validate() ) {\n\t\t\tif (isset(\$this->request->post['soconfig_general_store'])) {\n\t\t\t\t\$this->request->post['soconfig_general_store']['typeheader'] = '1';\n\t\t\t\t\$this->request->post['soconfig_general_store']['typefooter'] = '2';\n\t\t\t}";
        $as_content = str_replace($as_target, $as_replace, $as_content);
        file_put_contents($admin_soconfig_path, $as_content);
        echo "✔ Patched admin soconfig.php (enforced typeheader=1 and typefooter=2).<br/>";
    }
}

// 5. Patch class/soconfig.php
$class_soconfig_path = __DIR__ . '/extension/so_theme/admin/view/template/soconfig/class/soconfig.php';
if (file_exists($class_soconfig_path)) {
    $cs_content = file_get_contents($class_soconfig_path);
    $cs_content = str_replace("\$themeCssHeader   \t= 'header/header'.\$typeheader.'.css';", "\$themeCssHeader   \t= 'header/header1.css';", $cs_content);
    $cs_content = str_replace("\$themeCssHeaderRTL  = 'header/header'.\$typeheader.'-rtl.css';", "\$themeCssHeaderRTL  = 'header/header1-rtl.css';", $cs_content);
    $cs_content = str_replace("\$themeCssFooter   \t= 'footer/footer'.\$typefooter.'.css';", "\$themeCssFooter   \t= 'footer/footer2.css';", $cs_content);
    $cs_content = str_replace("\$themeCssFooterRTL  = 'footer/footer'.\$typefooter.'-rtl.css';", "\$themeCssFooterRTL  = 'footer/footer2-rtl.css';", $cs_content);
    
    if (strpos($cs_content, 'Robust Fallback: Prevent empty') === false) {
        $cs_target_fb = "\$themeCssNameRTL = 'theme.css';\n\t\tendif;";
        $cs_replace_fb = "\$themeCssNameRTL = 'theme.css';\n\t\tendif;\n\n\t\t// Robust Fallback: Prevent empty or missing CSS files from breaking the layout\n\t\t\$cssFullPath = DIR_EXTENSION . 'so_theme/catalog/view/template/css/' . \$themeCssName;\n\t\tif (!file_exists(\$cssFullPath) || filesize(\$cssFullPath) === 0) {\n\t\t\t\$foundFallback = false;\n\t\t\t\$layoutDir = DIR_EXTENSION . 'so_theme/catalog/view/template/css/layout' . \$typelayout;\n\t\t\tif (is_dir(\$layoutDir)) {\n\t\t\t\tforeach (scandir(\$layoutDir) as \$f) {\n\t\t\t\t\tif (substr(\$f, -4) === '.css' && strpos(\$f, '-rtl') === false && filesize(\$layoutDir . '/' . \$f) > 0) {\n\t\t\t\t\t\t\$themeCssName = 'layout' . \$typelayout . '/' . \$f;\n\t\t\t\t\t\t\$foundFallback = true;\n\t\t\t\t\t\tbreak;\n\t\t\t\t\t}\n\t\t\t\t}\n\t\t\t}\n\t\t\tif (!\$foundFallback) {\n\t\t\t\t\$themeCssName = 'layout1/red.css';\n\t\t\t}\n\t\t}\n\t\t\$cssFullPathRTL = DIR_EXTENSION . 'so_theme/catalog/view/template/css/' . \$themeCssNameRTL;\n\t\tif (!file_exists(\$cssFullPathRTL) || filesize(\$cssFullPathRTL) === 0) {\n\t\t\t\$themeCssNameRTL = \$themeCssName;\n\t\t}";
        $cs_content = str_replace($cs_target_fb, $cs_replace_fb, $cs_content);
    }
    
    $cs_content = str_replace(
        "if (strpos(\$value, '-rtl') == false && strpos(\$value, '.css') == true)",
        "if (strpos(\$value, '-rtl') === false && strpos(\$value, '.css') !== false && filesize(\$log_directory . '/' . \$value) > 0)",
        $cs_content
    );
    file_put_contents($class_soconfig_path, $cs_content);
    echo "✔ Patched class/soconfig.php (header1/footer2 locked + 0-byte CSS fallback added).<br/>";
}

// 6. Patch header.twig & footer.twig
$header_twig_path = __DIR__ . '/extension/so_theme/catalog/view/template/common/header.twig';
if (file_exists($header_twig_path)) {
    $ht_content = file_get_contents($header_twig_path);
    if (strpos($ht_content, 'Fixed Header 1 for Magical Singing Bowls') === false) {
        $ht_pattern = '/\{\#\s*========\s*Show Header.*?\{\% endif \%\}/s';
        $ht_replace = "{# =========== Fixed Header 1 for Magical Singing Bowls============== #}\n{% include theme_directory~'/view/template/header/header1.twig' with {typeheader: '1'} %}";
        if (preg_match($ht_pattern, $ht_content)) {
            $ht_content = preg_replace($ht_pattern, $ht_replace, $ht_content);
            file_put_contents($header_twig_path, $ht_content);
            echo "✔ Patched header.twig (Header 1 locked).<br/>";
        }
    }
}

$footer_twig_path = __DIR__ . '/extension/so_theme/catalog/view/template/common/footer.twig';
if (file_exists($footer_twig_path)) {
    $ft_content = file_get_contents($footer_twig_path);
    if (strpos($ft_content, 'Fixed Footer 2 for Magical Singing Bowls') === false) {
        $ft_pattern = '/\{\#\s*========\s*Show Header.*?\{\% endif \%\}/s';
        $ft_replace = "{# =========== Fixed Footer 2 for Magical Singing Bowls==============#}\n{% include theme_directory~'/view/template/footer/footer2.twig' with {typefooter: '2'} %}";
        if (preg_match($ft_pattern, $ft_content)) {
            $ft_content = preg_replace($ft_pattern, $ft_replace, $ft_content);
            file_put_contents($footer_twig_path, $ft_content);
            echo "✔ Patched footer.twig (Footer 2 locked).<br/>";
        }
    }
}

// 7. Clear minify CSS cache
$minify_dir = __DIR__ . '/extension/so_theme/catalog/view/template/minify/';
if (is_dir($minify_dir)) {
    foreach (glob($minify_dir . '*.css') as $mf) {
        @unlink($mf);
    }
    echo "✔ Cleared theme minified CSS cache.<br/>";
}

echo "<h3 style='color: green;'>Layout decoupling & Header/Footer locking successfully executed!</h3>";

echo "<h2>All done! Remote site updated.</h2>";
