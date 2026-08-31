<?php
// Script to update database and clear cache on remote server
if (file_exists('config.php')) {
    require_once('config.php');
} else {
    die("config.php not found!");
}

$link = @mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$prefix = DB_PREFIX;

if (!$link) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "<h2>1. Updating Database on Server...</h2>";

// 1. Disable Module 82 (Armchair Banner)
$res = mysqli_query($link, "SELECT setting FROM {$prefix}module WHERE module_id = 82");
if ($row = mysqli_fetch_assoc($res)) {
    $setting = json_decode($row['setting'], true);
    $setting['status'] = "0";
    $new_setting = mysqli_real_escape_string($link, json_encode($setting));
    mysqli_query($link, "UPDATE {$prefix}module SET setting = '{$new_setting}' WHERE module_id = 82");
    echo "✔ Module 82 (Banner Left - Armchair) set to disabled.<br/>";
}

// 2. Remove Module 82 from oc_layout_module
mysqli_query($link, "DELETE FROM {$prefix}layout_module WHERE code = 'so_theme.so_html_content.82'");
echo "✔ Removed module 82 from layout_module.<br/>";

// 3. Register Supercheckout in oc_extension_install if missing
$check = mysqli_query($link, "SELECT * FROM {$prefix}extension_install WHERE code = 'supercheckout'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($link, "INSERT INTO {$prefix}extension_install (extension_id, extension_download_id, name, description, code, version, author, link, status, date_added) VALUES (0, 0, 'One Page Supercheckout', '', 'supercheckout', '4.4', 'Knowband', 'https://www.knowband.com', 1, NOW())");
    echo "✔ Supercheckout registered in extension_install table.<br/>";
} else {
    echo "✔ Supercheckout already present in extension_install table.<br/>";
}

// 4. Register Supercheckout in oc_extension if missing
$check_ext = mysqli_query($link, "SELECT * FROM {$prefix}extension WHERE type = 'module' AND code = 'supercheckout'");
if (mysqli_num_rows($check_ext) == 0) {
    mysqli_query($link, "INSERT INTO {$prefix}extension (extension, type, code) VALUES ('supercheckout', 'module', 'supercheckout')");
    echo "✔ Supercheckout registered in extension table.<br/>";
} else {
    echo "✔ Supercheckout already present in extension table.<br/>";
}

// 5. Register MCE Shipping Extensions (eco, pri, aramexpri) if missing
$shipping_codes = ['eco', 'pri', 'aramexpri'];
foreach ($shipping_codes as $scode) {
    $c = mysqli_query($link, "SELECT * FROM {$prefix}extension WHERE type = 'shipping' AND code = '{$scode}'");
    if (mysqli_num_rows($c) == 0) {
        mysqli_query($link, "INSERT INTO {$prefix}extension (extension, type, code) VALUES ('opencart', 'shipping', '{$scode}')");
        echo "✔ Shipping method '{$scode}' registered in extension table.<br/>";
    }
    $s_check = mysqli_query($link, "SELECT * FROM {$prefix}setting WHERE code = 'shipping_{$scode}' AND `key` = 'shipping_{$scode}_status'");
    if (mysqli_num_rows($s_check) == 0) {
        mysqli_query($link, "INSERT INTO {$prefix}setting (store_id, code, `key`, value, serialized) VALUES (0, 'shipping_{$scode}', 'shipping_{$scode}_status', '1', 0)");
        mysqli_query($link, "INSERT INTO {$prefix}setting (store_id, code, `key`, value, serialized) VALUES (0, 'shipping_{$scode}', 'shipping_{$scode}_cost', '0.001', 0)");
        mysqli_query($link, "INSERT INTO {$prefix}setting (store_id, code, `key`, value, serialized) VALUES (0, 'shipping_{$scode}', 'shipping_{$scode}_sort_order', '1', 0)");
        mysqli_query($link, "INSERT INTO {$prefix}setting (store_id, code, `key`, value, serialized) VALUES (0, 'shipping_{$scode}', 'shipping_{$scode}_geo_zone_id', '0', 0)");
        mysqli_query($link, "INSERT INTO {$prefix}setting (store_id, code, `key`, value, serialized) VALUES (0, 'shipping_{$scode}', 'shipping_{$scode}_tax_class_id', '0', 0)");
        echo "✔ Shipping method '{$scode}' settings enabled in setting table.<br/>";
    }
}

// 6. Fix UK Country Name matching for MCE Shipping Rates
mysqli_query($link, "UPDATE {$prefix}country_description SET name = 'United Kingdom (UK)' WHERE country_id = 222 AND name = 'United Kingdom'");
mysqli_query($link, "INSERT IGNORE INTO {$prefix}postage_country_time (country, aramex_zone_pri, fedex_zone_eco, fedex_zone_pri) VALUES ('United Kingdom', 'f', 'f', 'f')");
mysqli_query($link, "UPDATE {$prefix}postage_country_time SET fedex_zone_pri = 'f' WHERE country LIKE '%United Kingdom%' AND (fedex_zone_pri IS NULL OR fedex_zone_pri = '')");
echo "✔ UK shipping country name mapping updated in database.<br/>";

// 7. Setup oc_modification table, permissions, and register extensions/modifications
mysqli_query($link, "CREATE TABLE IF NOT EXISTS `{$prefix}modification` (
  `modification_id` int(11) NOT NULL AUTO_INCREMENT,
  `extension_install_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `code` varchar(64) NOT NULL,
  `author` varchar(64) NOT NULL,
  `version` varchar(32) NOT NULL,
  `link` varchar(255) NOT NULL,
  `xml` mediumtext NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`modification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
echo "✔ Table oc_modification is ready.<br/>";

// Update Administrator User Group Permissions (user_group_id = 1)
$res_ug = mysqli_query($link, "SELECT permission FROM `{$prefix}user_group` WHERE user_group_id = 1");
if ($row_ug = mysqli_fetch_assoc($res_ug)) {
    $permissions = json_decode($row_ug['permission'], true);
    if (!is_array($permissions)) {
        $permissions = ['access' => [], 'modify' => []];
    }
    $routes_to_add = [
        'marketplace/modification',
        'extension/huntbee',
        'extension/huntbee/module/base_plugin',
        'extension/huntbee/module/hb_cart',
        'extension/huntbee/module/order_review',
        'extension/tmd',
        'extension/tmd/other/import',
        'extension/tmd/other/export',
        'extension/opencart/other/arameximport',
        'extension/opencart/other/aramexratesimport',
        'extension/opencart/other/fedexratesimport'
    ];
    foreach ($routes_to_add as $route) {
        if (!in_array($route, $permissions['access'])) {
            $permissions['access'][] = $route;
        }
        if (!in_array($route, $permissions['modify'])) {
            $permissions['modify'][] = $route;
        }
    }
    $updated_permission_json = mysqli_real_escape_string($link, json_encode($permissions));
    mysqli_query($link, "UPDATE `{$prefix}user_group` SET permission = '{$updated_permission_json}' WHERE user_group_id = 1");
    echo "✔ Updated Administrator permissions for Modifications and HuntBee/TMD extensions.<br/>";
}

// Seed / Register Modifications in oc_modification
$server_mods = [
    [
        'name' => 'Abandoned Cart - MarketinSG Quick Checkout Patch',
        'code' => 'hb_cart_patch',
        'author' => 'HuntBee OpenCart Services',
        'version' => '1.0.0',
        'date_added' => '2023-05-18 10:00:00',
        'description' => 'Patch for MarketinSG Quick Checkout compatibility with Abandoned Cart Email',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_cart_patch</code><name>Abandoned Cart - MarketinSG Quick Checkout Patch</name><version>1.0.0</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'Abandoned Cart Email (3xxx)',
        'code' => 'hb_cart',
        'author' => 'HuntBee OpenCart Services',
        'version' => '3.1.5',
        'date_added' => '2025-04-28 10:00:00',
        'description' => 'Automated & Manual Abandoned Cart Email Notifications and Popup',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_cart</code><name>Abandoned Cart Email (3xxx)</name><version>3.1.5</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'Base Plugin from HuntBee (3xxx)',
        'code' => 'hb_base',
        'author' => 'HuntBee OpenCart Services',
        'version' => '3.0.0',
        'date_added' => '2023-05-16 10:00:00',
        'description' => 'Base Core Plugin for HuntBee Extensions',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_base</code><name>Base Plugin from HuntBee (3xxx)</name><version>3.0.0</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'FeedbackFlow: Post-Purchase Review Invitation',
        'code' => 'hb_order_review',
        'author' => 'HuntBee OpenCart Services',
        'version' => '3.2.3',
        'date_added' => '2025-04-28 10:00:00',
        'description' => 'FeedbackFlow Post-Purchase Customer Review Invitations',
        'link' => 'https://www.huntbee.com',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>hb_order_review</code><name>FeedbackFlow: Post-Purchase Review Invitation</name><version>3.2.3</version><author>HuntBee OpenCart Services</author></modification>'
    ],
    [
        'name' => 'TMD Import Export Module',
        'code' => 'tmd_import_export',
        'author' => 'TMD(opencartextensions.in)',
        'version' => '3.x',
        'date_added' => '2018-11-05 10:00:00',
        'description' => 'TMD Excel Import Export Module for OpenCart',
        'link' => 'http://opencartextensions.in/',
        'xml' => '<?xml version="1.0" encoding="utf-8"?><modification><code>tmd_import_export</code><name>TMD Import Export Module</name><version>3.x</version><author>TMD(opencartextensions.in)</author></modification>'
    ]
];

foreach ($server_mods as $m) {
    $m_name = mysqli_real_escape_string($link, $m['name']);
    $m_code = mysqli_real_escape_string($link, $m['code']);
    $m_author = mysqli_real_escape_string($link, $m['author']);
    $m_version = mysqli_real_escape_string($link, $m['version']);
    $m_date_added = mysqli_real_escape_string($link, $m['date_added']);
    $m_description = mysqli_real_escape_string($link, $m['description']);
    $m_link = mysqli_real_escape_string($link, $m['link']);
    $m_xml = mysqli_real_escape_string($link, $m['xml']);

    $check_m = mysqli_query($link, "SELECT modification_id FROM `{$prefix}modification` WHERE code = '{$m_code}' OR name = '{$m_name}'");
    if (mysqli_num_rows($check_m) == 0) {
        mysqli_query($link, "INSERT INTO `{$prefix}modification` (`extension_install_id`, `name`, `description`, `code`, `author`, `version`, `link`, `xml`, `status`, `date_added`) 
                             VALUES (0, '{$m_name}', '{$m_description}', '{$m_code}', '{$m_author}', '{$m_version}', '{$m_link}', '{$m_xml}', 1, '{$m_date_added}')");
        echo "✔ Registered modification: <b>{$m['name']}</b>.<br/>";
    } else {
        echo "✔ Modification already present: <b>{$m['name']}</b>.<br/>";
    }
}

// 8. Register TMD and HuntBee in extension_install and extension tables
$check_tmd = mysqli_query($link, "SELECT * FROM `{$prefix}extension_install` WHERE code = 'tmd'");
if (mysqli_num_rows($check_tmd) == 0) {
    mysqli_query($link, "INSERT INTO `{$prefix}extension_install` (extension_id, extension_download_id, name, description, code, version, author, link, status, date_added) VALUES (0, 0, 'TMD Import Export Module', '', 'tmd', '3.x', 'TMD(opencartextensions.in)', 'http://opencartextensions.in/', 1, NOW())");
    echo "✔ Registered TMD in extension_install table.<br/>";
} else {
    echo "✔ TMD already present in extension_install table.<br/>";
}

$check_hb = mysqli_query($link, "SELECT * FROM `{$prefix}extension_install` WHERE code = 'huntbee'");
if (mysqli_num_rows($check_hb) == 0) {
    mysqli_query($link, "INSERT INTO `{$prefix}extension_install` (extension_id, extension_download_id, name, description, code, version, author, link, status, date_added) VALUES (0, 0, 'HuntBee OpenCart Extensions', '', 'huntbee', '3.2.3', 'HuntBee OpenCart Services', 'https://www.huntbee.com', 1, NOW())");
    echo "✔ Registered HuntBee in extension_install table.<br/>";
} else {
    echo "✔ HuntBee already present in extension_install table.<br/>";
}

$ext_records = [
    ['tmd', 'other', 'import'],
    ['tmd', 'other', 'export'],
    ['huntbee', 'module', 'base_plugin'],
    ['huntbee', 'module', 'hb_cart'],
    ['huntbee', 'module', 'order_review']
];
foreach ($ext_records as $er) {
    $c = mysqli_query($link, "SELECT * FROM `{$prefix}extension` WHERE extension = '{$er[0]}' AND type = '{$er[1]}' AND code = '{$er[2]}'");
    if (mysqli_num_rows($c) == 0) {
        mysqli_query($link, "INSERT INTO `{$prefix}extension` (extension, type, code) VALUES ('{$er[0]}', '{$er[1]}', '{$er[2]}')");
        echo "✔ Registered extension {$er[0]}/{$er[2]} in extension table.<br/>";
    }
}

// 9. Deploy / Write Extension Files on Server Filesystem
function ensure_file_written($file_path, $content) {
    $dir = dirname($file_path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents($file_path, $content);
}

// TMD install.json
ensure_file_written(__DIR__ . '/extension/tmd/install.json', json_encode([
    'name' => 'TMD Import Export Module',
    'version' => '3.x',
    'author' => 'TMD(opencartextensions.in)',
    'link' => 'http://opencartextensions.in/'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// TMD Export Controller
ensure_file_written(__DIR__ . '/extension/tmd/admin/controller/other/export.php', '<?php
namespace Opencart\Admin\Controller\Extension\Tmd\Other;

/**
 * TMD Export Module Controller for OpenCart 4
 */
class Export extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/tmd/other/export\');

		$this->document->setTitle($this->language->get(\'heading_title\'));

		$data[\'breadcrumbs\'] = [];

		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/tmd/other/export\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'export_xlsx\'] = $this->url->link(\'extension/tmd/other/export.download\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&format=xlsx\');
		$data[\'export_xls\']  = $data[\'export_xlsx\'];
		$data[\'export_csv\']  = $this->url->link(\'extension/tmd/other/export.download\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&format=csv\');

		$data[\'user_token\'] = $this->session->data[\'user_token\'];

		$count_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`");
		$data[\'total_products\'] = (int)$count_query->row[\'total\'];

		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/tmd/other/export\', $data));
	}

	public function download(): void {
		@set_time_limit(0);
		@ini_set(\'memory_limit\', \'2048M\');

		$this->load->language(\'extension/tmd/other/export\');

		if (!$this->user->hasPermission(\'modify\', \'extension/tmd/other/export\')) {
			$this->session->data[\'error\'] = $this->language->get(\'error_permission\');
			$this->response->redirect($this->url->link(\'extension/tmd/other/export\', \'user_token=\' . $this->session->data[\'user_token\']));
			return;
		}

		$format = strtolower($this->request->get[\'format\'] ?? \'xlsx\');
		if ($format !== \'csv\') {
			$format = \'xlsx\';
		}

		$language_id = (int)$this->config->get(\'config_language_id\');

		$this->load->model(\'localisation/language\');
		$language_info = $this->model_localisation_language->getLanguage($language_id);
		$language_code = $language_info[\'code\'] ?? \'en-gb\';

		// Exact 57 TMD Header Columns
		$headers = [
			\'Product ID\',
			\'Language\',
			\'Stores\',
			\'Stores id (0=Store;1=next if presemt) (1=2)\',
			\'Model\',
			\'SKU\',
			\'UPC\',
			\'EAN\',
			\'JAN\',
			\'ISBN\',
			\'MPN\',
			\'Location\',
			\'Product Name\',
			\'Meta Tag Description\',
			\'Meta Tag Keywords\',
			\'Description\',
			\'Product Tags\',
			\'Price\',
			\'Quantity\',
			\'Minimum Quantity\',
			\'Subtract Stock  (1=YES 0= NO)\',
			\'Out Of Stock Status  (5=Out Of Stock , 8=Pre-Order , In Stock=7, 6=2 - 3 Days)\',
			\'Requires Shipping (1=YES 0= NO)\',
			\'SEO Keyword  (Must Unquie)\',
			\'Image(Main image)\',
			\'Date Available (Y-m-d)\',
			\'Length Class (1=Centimeter, 3=Inch, 2=Millimeter)\',
			\'Length\',
			\'Width\',
			\'height\',
			\'Weight\',
			\'Weight Class  (1=Kilogram,2=Gram,6=Ounce,Pound=5)\',
			\'Status (1=Enabled, 0= Disabled)\',
			\'Sort Order\',
			\'Manufacturer ID\',
			\'Manufacturer\',
			\'Categories id\',
			\'Categories (category>subcategory; category1>subcategory1 )\',
			\'Related Product ID(productid,productid)\',
			\'Related Product (model,model)\',
			\'Option (name and type) size:select;color:radio\',
			\'option:value1-qty-Subtract Stock-Price-Points-Weight;option:value1-qty-Subtract Stock-Price-Points-Weight\',
			\'(image1;image2;image3)\',
			\'Product Special price:(customer_group_id:start date:end date: special price )\',
			\'Tax Class (None=0,Taxable Goods=9,Downloadable Products=10) Rest you can make and put that ID\',
			\'Filter Group Name      (Group Name: Sort order;Group Name: Sort order)\',
			\'Filter names (group name=name:sort order;group name=name:sort order)\',
			\'Attributes (Attribute group name:sort order=atrribute name-value-sort order;Attribute group name:sort order=atrribute name-value-sort order;)\',
			\'Discount (customer_group_id:qty:Priority:Price-Date Start-Date End;customer_group_id:qty:Priority:Price-Date Start-Date End;)\',
			\'Reward Points\',
			\'Meta Title\',
			\'Viewed\',
			\'Download id\',
			\'Reviews(Customer ID::author::text::ratting::status::date_added::date_modified|Customer ID::author::text::ratting::status::date_added::date_modified)\',
			\'Diameter\',
			\'Recomended Product ID(productid,productid)\',
			\'Recomended Product (model,model)\'
		];

		$has_rec1 = $this->db->query("SHOW TABLES LIKE \'" . DB_PREFIX . "product_recomended\'")->num_rows;
		$has_rec2 = $this->db->query("SHOW TABLES LIKE \'" . DB_PREFIX . "product_recommended\'")->num_rows;
		$has_special_table = $this->db->query("SHOW TABLES LIKE \'" . DB_PREFIX . "product_special\'")->num_rows;

		$sql = "SELECT p.*, pd.name, pd.meta_description, pd.meta_keyword, pd.description, pd.tag, pd.meta_title, pd.diameter 
				FROM `" . DB_PREFIX . "product` p 
				LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = \'" . (int)$language_id . "\') 
				ORDER BY p.product_id ASC";

		$query = $this->db->query($sql);

		$rows_data = [];

		foreach ($query->rows as $row) {
			$product_id = (int)$row[\'product_id\'];

			// 1. Stores
			$stores = \'\';
			$storeids = \'\';
			$store_query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = \'" . $product_id . "\'");
			if ($store_query->rows) {
				foreach ($store_query->rows as $s_row) {
					if ($s_row[\'store_id\'] == 0) {
						$stores .= \'default;\';
						$storeids .= \'0;\';
					} else {
						$st_query = $this->db->query("SELECT name FROM `" . DB_PREFIX . "store` WHERE store_id = \'" . (int)$s_row[\'store_id\'] . "\'");
						if ($st_query->row) {
							$stores .= $st_query->row[\'name\'] . \';\';
							$storeids .= $s_row[\'store_id\'] . \';\';
						}
					}
				}
			} else {
				$stores = \'default;\';
				$storeids = \'0;\';
			}

			// 2. SEO Keyword
			$seo_keyword = \'\';
			$seo_query = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `key` = \'product_id\' AND `value` = \'" . $product_id . "\' AND `language_id` = \'" . (int)$language_id . "\' LIMIT 1");
			if ($seo_query->row) {
				$seo_keyword = $seo_query->row[\'keyword\'];
			} else {
				$seo_query2 = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `key` = \'product_id\' AND `value` = \'" . $product_id . "\' LIMIT 1");
				if ($seo_query2->row) {
					$seo_keyword = $seo_query2->row[\'keyword\'];
				}
			}

			// 3. Manufacturer
			$manufacturer = \'\';
			$manufacturerid = \'\';
			if (!empty($row[\'manufacturer_id\'])) {
				$m_query = $this->db->query("SELECT manufacturer_id, name FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = \'" . (int)$row[\'manufacturer_id\'] . "\'");
				if ($m_query->row) {
					$manufacturerid = $m_query->row[\'manufacturer_id\'];
					$manufacturer = $m_query->row[\'name\'];
				}
			}

			// 4. Categories & Category IDs
			$categories = \'\';
			$categoriesid = \'\';
			$cat_query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = \'" . $product_id . "\'");
			foreach ($cat_query->rows as $cat_row) {
				$categoriesid .= $cat_row[\'category_id\'] . \';\';
				$path_query = $this->db->query("SELECT GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR \' > \') AS name 
												FROM `" . DB_PREFIX . "category_path` cp 
												LEFT JOIN `" . DB_PREFIX . "category_description` cd1 ON (cp.path_id = cd1.category_id AND cd1.language_id = \'" . (int)$language_id . "\') 
												WHERE cp.category_id = \'" . (int)$cat_row[\'category_id\'] . "\' 
												GROUP BY cp.category_id");
				if ($path_query->row && !empty($path_query->row[\'name\'])) {
					$categories .= $path_query->row[\'name\'] . \';\';
				}
			}

			// 5. Related Products
			$related = \'\';
			$relatedid = \'\';
			$rel_query = $this->db->query("SELECT pn.model, pn.product_id FROM `" . DB_PREFIX . "product_related` pr LEFT JOIN `" . DB_PREFIX . "product` pn ON (pn.product_id = pr.related_id) WHERE pr.product_id = \'" . $product_id . "\'");
			foreach ($rel_query->rows as $rp) {
				if ($rp[\'product_id\']) {
					$relatedid .= $rp[\'product_id\'] . \';\';
					$related .= ($rp[\'model\'] ?? \'\') . \';\';
				}
			}

			// 6. Recommended Products
			$recomended = \'\';
			$recomendedid = \'\';
			if ($has_rec1) {
				$rec_query = $this->db->query("SELECT pn.model, pn.product_id FROM `" . DB_PREFIX . "product_recomended` pr LEFT JOIN `" . DB_PREFIX . "product` pn ON (pn.product_id = pr.recomended_id) WHERE pr.product_id = \'" . $product_id . "\'");
				foreach ($rec_query->rows as $rp) {
					if ($rp[\'product_id\']) {
						$recomendedid .= $rp[\'product_id\'] . \';\';
						$recomended .= ($rp[\'model\'] ?? \'\') . \';\';
					}
				}
			} elseif ($has_rec2) {
				$rec_query = $this->db->query("SELECT pn.model, pn.product_id FROM `" . DB_PREFIX . "product_recommended` pr LEFT JOIN `" . DB_PREFIX . "product` pn ON (pn.product_id = pr.recommended_id) WHERE pr.product_id = \'" . $product_id . "\'");
				foreach ($rec_query->rows as $rp) {
					if ($rp[\'product_id\']) {
						$recomendedid .= $rp[\'product_id\'] . \';\';
						$recomended .= ($rp[\'model\'] ?? \'\') . \';\';
					}
				}
			}

			// 7. Options & Option Values
			$options = \'\';
			$optionvalue = \'\';
			$opt_query = $this->db->query("SELECT po.option_id, po.product_option_id, od.name, o.type 
										   FROM `" . DB_PREFIX . "product_option` po 
										   LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = po.option_id AND od.language_id = \'" . (int)$language_id . "\') 
										   LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) 
										   WHERE po.product_id = \'" . $product_id . "\'");
			foreach ($opt_query->rows as $option) {
				$opt_name = str_replace(\'-\', \'/\', $option[\'name\'] ?? \'\');
				$options .= str_replace(\'&amp;\', \'&\', $opt_name) . \':\' . ($option[\'type\'] ?? \'\') . \';\';

				$opt_val_query = $this->db->query("SELECT pov.*, ovd.name AS val_name 
												   FROM `" . DB_PREFIX . "product_option_value` pov 
												   LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id AND ovd.language_id = \'" . (int)$language_id . "\') 
												   WHERE pov.product_option_id = \'" . (int)$option[\'product_option_id\'] . "\'");
				foreach ($opt_val_query->rows as $pov) {
					$val_name = str_replace(\'-\', \'/\', $pov[\'val_name\'] ?? \'\');
					$optionvalue .= str_replace(\'&amp;\', \'&\', $opt_name) . \':\' . str_replace(\'&amp;\', \'&\', $val_name) . \'-\' . $pov[\'quantity\'] . \'-\' . $pov[\'subtract\'] . \'-\' . round((float)$pov[\'price\'], 2) . \'-\' . $pov[\'points\'] . \'-\' . round((float)$pov[\'weight\'], 2) . \';\';
				}
			}

			// 8. Additional Images
			$images = \'\';
			$img_query = $this->db->query("SELECT image FROM `" . DB_PREFIX . "product_image` WHERE product_id = \'" . $product_id . "\' ORDER BY sort_order ASC");
			foreach ($img_query->rows as $img_row) {
				$images .= $img_row[\'image\'] . \';\';
			}

			// 9. Special Price
			$product_sp = \'\';
			if ($has_special_table) {
				$sp_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_special` WHERE product_id = \'" . $product_id . "\' ORDER BY product_special_id DESC");
			} else {
				$sp_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = \'" . $product_id . "\' AND `special` = \'1\' ORDER BY product_discount_id DESC");
			}
			foreach ($sp_query->rows as $sp) {
				$product_sp .= $sp[\'customer_group_id\'] . \':\' . $sp[\'date_start\'] . \':\' . $sp[\'date_end\'] . \':\' . $sp[\'price\'] . \';\';
			}

			// 10. Filters
			$filter_group = \'\';
			$filter_name = \'\';
			$fg_query = $this->db->query("SELECT DISTINCT fgd.name, fg.sort_order 
										  FROM `" . DB_PREFIX . "product_filter` pf 
										  LEFT JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id) 
										  LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (fgd.filter_group_id = f.filter_group_id AND fgd.language_id = \'" . (int)$language_id . "\') 
										  LEFT JOIN `" . DB_PREFIX . "filter_group` fg ON (fg.filter_group_id = f.filter_group_id) 
										  WHERE pf.product_id = \'" . $product_id . "\'");
			foreach ($fg_query->rows as $fg_row) {
				if (!empty($fg_row[\'name\'])) {
					$filter_group .= $fg_row[\'name\'] . \':\' . ($fg_row[\'sort_order\'] ?? \'0\') . \';\';
				}
			}

			$fn_query = $this->db->query("SELECT fgd.name AS groupname, fd.name AS name, f.sort_order 
										  FROM `" . DB_PREFIX . "product_filter` pf 
										  LEFT JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id) 
										  LEFT JOIN `" . DB_PREFIX . "filter_description` fd ON (fd.filter_id = pf.filter_id AND fd.language_id = \'" . (int)$language_id . "\') 
										  LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (fgd.filter_group_id = f.filter_group_id AND fgd.language_id = \'" . (int)$language_id . "\') 
										  WHERE pf.product_id = \'" . $product_id . "\'");
			foreach ($fn_query->rows as $fn_row) {
				if (!empty($fn_row[\'groupname\']) && !empty($fn_row[\'name\'])) {
					$filter_name .= $fn_row[\'groupname\'] . \'=\' . $fn_row[\'name\'] . \':\' . ($fn_row[\'sort_order\'] ?? \'0\') . \';\';
				}
			}

			// 11. Attributes
			$atts = \'\';
			$att_query = $this->db->query("SELECT agd.name AS groupname, ag.sort_order AS groupsort, ad.name AS attname, a.sort_order AS attsort, pa.text 
										   FROM `" . DB_PREFIX . "product_attribute` pa 
										   LEFT JOIN `" . DB_PREFIX . "attribute` a ON (a.attribute_id = pa.attribute_id) 
										   LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON (ad.attribute_id = pa.attribute_id AND ad.language_id = \'" . (int)$language_id . "\') 
										   LEFT JOIN `" . DB_PREFIX . "attribute_group` ag ON (ag.attribute_group_id = a.attribute_group_id) 
										   LEFT JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (agd.attribute_group_id = ag.attribute_group_id AND agd.language_id = \'" . (int)$language_id . "\') 
										   WHERE pa.product_id = \'" . $product_id . "\' AND pa.language_id = \'" . (int)$language_id . "\'");
			foreach ($att_query->rows as $att_row) {
				$atts .= ($att_row[\'groupname\'] ?? \'\') . \':\' . ($att_row[\'groupsort\'] ?? \'0\') . \'=\' . ($att_row[\'attname\'] ?? \'\') . \'-\' . ($att_row[\'text\'] ?? \'\') . \'-\' . ($att_row[\'attsort\'] ?? \'0\') . \';\';
			}

			// 12. Discounts
			$discounts = \'\';
			if ($has_special_table) {
				$disc_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = \'" . $product_id . "\'");
			} else {
				$disc_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = \'" . $product_id . "\' AND `special` = \'0\'");
			}
			foreach ($disc_query->rows as $disc_row) {
				$discounts .= $disc_row[\'customer_group_id\'] . \':\' . $disc_row[\'quantity\'] . \':\' . $disc_row[\'priority\'] . \':\' . $disc_row[\'price\'] . \'-\' . $disc_row[\'date_start\'] . \'-\' . $disc_row[\'date_end\'] . \';\';
			}

			// 13. Downloads
			$downloadids = \'\';
			$dl_query = $this->db->query("SELECT download_id FROM `" . DB_PREFIX . "product_to_download` WHERE product_id = \'" . $product_id . "\'");
			foreach ($dl_query->rows as $dl_row) {
				$downloadids .= $dl_row[\'download_id\'] . \';\';
			}

			// 14. Reviews
			$reviews = \'\';
			$rev_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review` WHERE product_id = \'" . $product_id . "\'");
			foreach ($rev_query->rows as $rev_row) {
				$reviews .= $rev_row[\'customer_id\'] . \'::\' . $rev_row[\'author\'] . \'::\' . $rev_row[\'text\'] . \'::\' . $rev_row[\'rating\'] . \'::\' . $rev_row[\'status\'] . \'::\' . $rev_row[\'date_added\'] . \'::\' . $rev_row[\'date_modified\'] . \'|\';
			}

			// 15. Diameter
			$diameter = $row[\'diameter\'] ?? \'\';

			$rows_data[] = [
				$product_id,                                           // Product ID
				$language_code,                                        // Language
				$stores,                                               // Stores
				$storeids,                                             // Stores id
				$row[\'model\'] ?? \'\',                                   // Model
				$row[\'sku\'] ?? \'\',                                     // SKU
				$row[\'upc\'] ?? \'\',                                     // UPC
				$row[\'ean\'] ?? \'\',                                     // EAN
				$row[\'jan\'] ?? \'\',                                     // JAN
				$row[\'isbn\'] ?? \'\',                                    // ISBN
				$row[\'mpn\'] ?? \'\',                                     // MPN
				$row[\'location\'] ?? \'\',                                // Location
				$row[\'name\'] ?? \'\',                                    // Product Name
				$row[\'meta_description\'] ?? \'\',                         // Meta Tag Description
				$row[\'meta_keyword\'] ?? \'\',                             // Meta Tag Keywords
				html_entity_decode((string)($row[\'description\'] ?? \'\'), ENT_QUOTES, \'UTF-8\'), // Description
				$row[\'tag\'] ?? \'\',                                     // Product Tags
				$row[\'price\'] ?? 0,                                    // Price
				$row[\'quantity\'] ?? 0,                                 // Quantity
				$row[\'minimum\'] ?? 1,                                  // Minimum Quantity
				$row[\'subtract\'] ?? 1,                                 // Subtract Stock
				$row[\'stock_status_id\'] ?? 7,                          // Out Of Stock Status
				$row[\'shipping\'] ?? 1,                                 // Requires Shipping
				$seo_keyword,                                          // SEO Keyword
				$row[\'image\'] ?? \'\',                                   // Image(Main image)
				$row[\'date_available\'] ?? \'\',                          // Date Available
				$row[\'length_class_id\'] ?? 1,                          // Length Class
				$row[\'length\'] ?? 0,                                   // Length
				$row[\'width\'] ?? 0,                                    // Width
				$row[\'height\'] ?? 0,                                   // height
				$row[\'weight\'] ?? 0,                                   // Weight
				$row[\'weight_class_id\'] ?? 1,                          // Weight Class
				$row[\'status\'] ?? 1,                                   // Status
				$row[\'sort_order\'] ?? 0,                               // Sort Order
				$manufacturerid,                                       // Manufacturer ID
				$manufacturer,                                         // Manufacturer
				$categoriesid,                                         // Categories id
				$categories,                                           // Categories
				$relatedid,                                            // Related Product ID
				$related,                                              // Related Product (model,model)
				$options,                                              // Option (name and type)
				$optionvalue,                                          // option:value1-qty...
				$images,                                               // (image1;image2;image3)
				$product_sp,                                           // Product Special price
				$row[\'tax_class_id\'] ?? 0,                             // Tax Class
				$filter_group,                                         // Filter Group Name
				$filter_name,                                          // Filter names
				$atts,                                                 // Attributes
				$discounts,                                            // Discount
				$row[\'points\'] ?? 0,                                   // Reward Points
				$row[\'meta_title\'] ?? \'\',                              // Meta Title
				$row[\'viewed\'] ?? 0,                                   // Viewed
				$downloadids,                                          // Download id
				$reviews,                                              // Reviews
				$diameter,                                             // Diameter
				$recomendedid,                                         // Recomended Product ID
				$recomended                                            // Recomended Product (model,model)
			];
		}

		if ($format == \'csv\') {
			$filename = \'Product_\' . date(\'Y-m-d\') . \'.csv\';

			$fp = fopen(\'php://temp\', \'r+\');
			// Write UTF-8 BOM for Excel compatibility
			fputs($fp, "\xEF\xBB\xBF");
			fputcsv($fp, $headers);

			foreach ($rows_data as $data_row) {
				fputcsv($fp, $data_row);
			}

			rewind($fp);
			$output = stream_get_contents($fp);
			fclose($fp);

			$content_type = \'text/csv; charset=UTF-8\';
		} else {
			$filename = \'Product_\' . date(\'Y-m-d\') . \'.xlsx\';
			$output = $this->generateXlsx($headers, $rows_data);
			$content_type = \'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\';
		}

		if (function_exists(\'session_write_close\')) {
			@session_write_close();
		}

		while (ob_get_level()) {
			ob_end_clean();
		}

		header(\'Pragma: public\');
		header(\'Expires: 0\');
		header(\'Cache-Control: must-revalidate, post-check=0, pre-check=0\');
		header(\'Content-Description: File Transfer\');
		header(\'Content-Type: \' . $content_type);
		header(\'Content-Disposition: attachment; filename="\' . $filename . \'"\');
		header(\'Content-Transfer-Encoding: binary\');
		header(\'Content-Length: \' . strlen($output));

		echo $output;
		exit(0);
	}

	private function numToCol(int $n): string {
		$col = \'\';
		while ($n >= 0) {
			$col = chr(($n % 26) + 65) . $col;
			$n = intdiv($n, 26) - 1;
		}
		return $col;
	}

	private function generateXlsx(array $headers, array $rows): string {
		$zipFile = tempnam(sys_get_temp_dir(), \'xlsx_\');
		$zip = new \ZipArchive();
		if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			throw new \Exception(\'Failed to create ZIP archive for XLSX export.\');
		}

		$contentTypes = \'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>\';
		$zip->addFromString(\'[Content_Types].xml\', $contentTypes);

		$rels = \'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>\';
		$zip->addFromString(\'_rels/.rels\', $rels);

		$wbRels = \'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>\';
		$zip->addFromString(\'xl/_rels/workbook.xml.rels\', $wbRels);

		$workbook = \'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Product" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>\';
		$zip->addFromString(\'xl/workbook.xml\', $workbook);

		$styles = \'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill fillType="none"/></fill>
    <fill><patternFill fillType="gray125"/></fill>
    <fill><patternFill fillType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>\';
		$zip->addFromString(\'xl/styles.xml\', $styles);

		$sheetXmlFile = tempnam(sys_get_temp_dir(), \'sxml_\');
		$sfp = fopen($sheetXmlFile, \'w\');
		fwrite($sfp, \'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>\' . "\n");
		fwrite($sfp, \'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>\' . "\n");

		$rowNum = 1;
		fwrite($sfp, \'<row r="1">\' . "\n");
		foreach ($headers as $cIdx => $hText) {
			$ref = $this->numToCol($cIdx) . $rowNum;
			$escaped = htmlspecialchars((string)$hText, ENT_XML1 | ENT_QUOTES, \'UTF-8\');
			fwrite($sfp, \'<c r="\' . $ref . \'" t="inlineStr" s="1"><is><t>\' . $escaped . \'</t></is></c>\');
		}
		fwrite($sfp, "\n" . \'</row>\' . "\n");

		foreach ($rows as $rData) {
			$rowNum++;
			fwrite($sfp, \'<row r="\' . $rowNum . \'">\' . "\n");
			foreach ($rData as $cIdx => $val) {
				$ref = $this->numToCol($cIdx) . $rowNum;
				$valStr = (string)$val;
				// Clean invalid XML 1.0 control characters
				$valStr = preg_replace(\'/[\x00-\x08\x0B\x0C\x0E-\x1F]/\', \'\', $valStr);

				$escaped = htmlspecialchars($valStr, ENT_XML1 | ENT_QUOTES, \'UTF-8\');
				fwrite($sfp, \'<c r="\' . $ref . \'" t="inlineStr"><is><t>\' . $escaped . \'</t></is></c>\');
			}
			fwrite($sfp, "\n" . \'</row>\' . "\n");
		}

		fwrite($sfp, \'</sheetData></worksheet>\');
		fclose($sfp);

		$zip->addFile($sheetXmlFile, \'xl/worksheets/sheet1.xml\');
		$zip->close();
		@unlink($sheetXmlFile);

		$content = file_get_contents($zipFile);
		@unlink($zipFile);
		return $content;
	}
}');

// TMD Import Model
ensure_file_written(__DIR__ . '/extension/tmd/admin/model/other/import.php', '<?php
namespace Opencart\Admin\Model\Extension\Tmd\Other;

/**
 * TMD Import Model for OpenCart 4
 */
class Import extends \Opencart\System\Engine\Model {

	private ?bool $has_special_table = null;
	private ?string $recommended_table = null;

	public function hasSpecialTable(): bool {
		if ($this->has_special_table === null) {
			$this->has_special_table = ($this->db->query("SHOW TABLES LIKE \'" . DB_PREFIX . "product_special\'")->num_rows > 0);
		}
		return $this->has_special_table;
	}

	public function getRecommendedTable(): ?string {
		if ($this->recommended_table === null) {
			if ($this->db->query("SHOW TABLES LIKE \'" . DB_PREFIX . "product_recomended\'")->num_rows > 0) {
				$this->recommended_table = \'product_recomended\';
			} elseif ($this->db->query("SHOW TABLES LIKE \'" . DB_PREFIX . "product_recommended\'")->num_rows > 0) {
				$this->recommended_table = \'product_recommended\';
			} else {
				$this->recommended_table = \'\';
			}
		}
		return $this->recommended_table ?: null;
	}

	public function getProductByModel(string $model): int {
		$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE `model` = \'" . $this->db->escape(trim($model)) . "\' LIMIT 1");
		if ($query->num_rows) {
			return (int)$query->row[\'product_id\'];
		}
		return 0;
	}

	public function getCategoryByPath(string $path, int $parent_id = 0, int $store_id = 0, int $language_id = 1): int {
		$parts = explode(\'>\', $path);
		$current_parent_id = $parent_id;

		foreach ($parts as $name) {
			$name = trim($name);
			if ($name === \'\') continue;

			$query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c 
									  LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id) 
									  WHERE cd.name = \'" . $this->db->escape($name) . "\' 
									  AND c.parent_id = \'" . (int)$current_parent_id . "\' 
									  AND cd.language_id = \'" . (int)$language_id . "\' LIMIT 1");

			if ($query->num_rows) {
				$current_parent_id = (int)$query->row[\'category_id\'];
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET parent_id = \'" . (int)$current_parent_id . "\', `top` = \'" . ($current_parent_id == 0 ? 1 : 0) . "\', `column` = 1, sort_order = 0, status = 1, date_added = NOW(), date_modified = NOW()");
				$category_id = $this->db->getLastId();

				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET category_id = \'" . (int)$category_id . "\', language_id = \'" . (int)$language_id . "\', name = \'" . $this->db->escape($name) . "\', description = \'\', meta_title = \'" . $this->db->escape($name) . "\', meta_description = \'\', meta_keyword = \'\'");

				$level = 0;
				$path_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = \'" . (int)$current_parent_id . "\' ORDER BY `level` ASC");
				foreach ($path_query->rows as $result) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = \'" . (int)$category_id . "\', path_id = \'" . (int)$result[\'path_id\'] . "\', `level` = \'" . (int)$level . "\'");
					$level++;
				}
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = \'" . (int)$category_id . "\', path_id = \'" . (int)$category_id . "\', `level` = \'" . (int)$level . "\'");

				$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "category_to_store` SET category_id = \'" . (int)$category_id . "\', store_id = \'" . (int)$store_id . "\'");

				$current_parent_id = $category_id;
			}
		}

		return $current_parent_id;
	}

	public function getManufacturer(string $name, int $store_id = 0): int {
		$name = trim($name);
		if ($name === \'\') return 0;

		$query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE `name` = \'" . $this->db->escape($name) . "\' LIMIT 1");
		if ($query->num_rows) {
			return (int)$query->row[\'manufacturer_id\'];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name = \'" . $this->db->escape($name) . "\', sort_order = 0");
		$manufacturer_id = $this->db->getLastId();
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "manufacturer_to_store` SET manufacturer_id = \'" . (int)$manufacturer_id . "\', store_id = \'" . (int)$store_id . "\'");

		return $manufacturer_id;
	}

	public function getFilterId(string $group_name, string $filter_name, int $language_id = 1): int {
		$group_name = trim($group_name);
		$filter_name = trim($filter_name);
		if ($group_name === \'\' || $filter_name === \'\') return 0;

		$fg_query = $this->db->query("SELECT filter_group_id FROM `" . DB_PREFIX . "filter_group_description` WHERE `name` = \'" . $this->db->escape($group_name) . "\' AND language_id = \'" . (int)$language_id . "\' LIMIT 1");
		if ($fg_query->num_rows) {
			$filter_group_id = (int)$fg_query->row[\'filter_group_id\'];
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_group` SET sort_order = 0");
			$filter_group_id = $this->db->getLastId();
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_group_description` SET filter_group_id = \'" . (int)$filter_group_id . "\', language_id = \'" . (int)$language_id . "\', name = \'" . $this->db->escape($group_name) . "\'");
		}

		$f_query = $this->db->query("SELECT f.filter_id FROM `" . DB_PREFIX . "filter` f 
									LEFT JOIN `" . DB_PREFIX . "filter_description` fd ON (f.filter_id = fd.filter_id) 
									WHERE f.filter_group_id = \'" . (int)$filter_group_id . "\' 
									AND fd.name = \'" . $this->db->escape($filter_name) . "\' 
									AND fd.language_id = \'" . (int)$language_id . "\' LIMIT 1");

		if ($f_query->num_rows) {
			return (int)$f_query->row[\'filter_id\'];
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter` SET filter_group_id = \'" . (int)$filter_group_id . "\', sort_order = 0");
			$filter_id = $this->db->getLastId();
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_description` SET filter_id = \'" . (int)$filter_id . "\', language_id = \'" . (int)$language_id . "\', filter_group_id = \'" . (int)$filter_group_id . "\', name = \'" . $this->db->escape($filter_name) . "\'");
			return $filter_id;
		}
	}

	public function importProduct(array $row, int $default_language_id = 1, int $default_store_id = 0): array {
		$product_id_in  = isset($row[0]) && is_numeric($row[0]) && (int)$row[0] > 0 ? (int)$row[0] : 0;
		$model          = isset($row[4]) ? trim((string)$row[4]) : \'\';
		$sku            = isset($row[5]) ? trim((string)$row[5]) : \'\';
		$upc            = isset($row[6]) ? trim((string)$row[6]) : \'\';
		$ean            = isset($row[7]) ? trim((string)$row[7]) : \'\';
		$jan            = isset($row[8]) ? trim((string)$row[8]) : \'\';
		$isbn           = isset($row[9]) ? trim((string)$row[9]) : \'\';
		$mpn            = isset($row[10]) ? trim((string)$row[10]) : \'\';
		$location       = isset($row[11]) ? trim((string)$row[11]) : \'\';
		$name           = isset($row[12]) ? trim((string)$row[12]) : ($model ?: \'Product\');
		$meta_desc      = isset($row[13]) ? trim((string)$row[13]) : \'\';
		$meta_keywords  = isset($row[14]) ? trim((string)$row[14]) : \'\';
		$description    = isset($row[15]) ? trim((string)$row[15]) : \'\';
		$tags           = isset($row[16]) ? trim((string)$row[16]) : \'\';
		$price          = isset($row[17]) ? (float)$row[17] : 0.00;
		$quantity       = isset($row[18]) && is_numeric($row[18]) ? (int)$row[18] : 100;
		$minimum        = isset($row[19]) && is_numeric($row[19]) ? (int)$row[19] : 1;
		$subtract       = isset($row[20]) && is_numeric($row[20]) ? (int)$row[20] : 1;
		$stock_status_id= isset($row[21]) && is_numeric($row[21]) ? (int)$row[21] : 7;
		$shipping       = isset($row[22]) && is_numeric($row[22]) ? (int)$row[22] : 1;
		$seo_keyword    = isset($row[23]) ? trim((string)$row[23]) : \'\';
		$image          = isset($row[24]) ? trim((string)$row[24]) : \'\';
		$date_avail     = isset($row[25]) && !empty($row[25]) ? date(\'Y-m-d\', strtotime($row[25])) : date(\'Y-m-d\');
		$length_class_id= isset($row[26]) && is_numeric($row[26]) ? (int)$row[26] : 1;
		$length         = isset($row[27]) ? (float)$row[27] : 0.00;
		$width          = isset($row[28]) ? (float)$row[28] : 0.00;
		$height         = isset($row[29]) ? (float)$row[29] : 0.00;
		$weight         = isset($row[30]) ? (float)$row[30] : 0.00;
		$weight_class_id= isset($row[31]) && is_numeric($row[31]) ? (int)$row[31] : 2;
		$status         = isset($row[32]) && is_numeric($row[32]) ? (int)$row[32] : 1;
		$sort_order     = isset($row[33]) && is_numeric($row[33]) ? (int)$row[33] : 0;
		$manufacturer_id= isset($row[34]) && is_numeric($row[34]) && (int)$row[34] > 0 ? (int)$row[34] : 0;
		$manufacturer_str=isset($row[35]) ? trim((string)$row[35]) : \'\';
		$cat_ids_str    = isset($row[36]) ? trim((string)$row[36]) : \'\';
		$categories_str = isset($row[37]) ? trim((string)$row[37]) : \'\';
		$rel_models_str = isset($row[39]) ? trim((string)$row[39]) : \'\';
		$extra_imgs_str = isset($row[42]) ? trim((string)$row[42]) : \'\';
		$specials_str   = isset($row[43]) ? trim((string)$row[43]) : \'\';
		$tax_class_id   = isset($row[44]) && is_numeric($row[44]) ? (int)$row[44] : 0;
		$filter_names_str=isset($row[46]) ? trim((string)$row[46]) : \'\';
		$discounts_str  = isset($row[48]) ? trim((string)$row[48]) : \'\';
		$meta_title     = isset($row[50]) && !empty($row[50]) ? trim((string)$row[50]) : $name;
		$diameter       = isset($row[54]) ? trim((string)$row[54]) : \'\';
		$rec_models_str = isset($row[56]) ? trim((string)$row[56]) : \'\';

		if (!$manufacturer_id && !empty($manufacturer_str)) {
			$manufacturer_id = $this->getManufacturer($manufacturer_str, $default_store_id);
		}

		$product_id = 0;
		if ($product_id_in > 0) {
			$chk = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE product_id = \'" . (int)$product_id_in . "\' LIMIT 1");
			if ($chk->num_rows) {
				$product_id = (int)$chk->row[\'product_id\'];
			}
		}
		if (!$product_id && !empty($model)) {
			$product_id = $this->getProductByModel($model);
		}

		$is_new = ($product_id == 0);

		if ($is_new) {
			$sql = "INSERT INTO `" . DB_PREFIX . "product` SET 
					model = \'" . $this->db->escape($model) . "\',
					sku = \'" . $this->db->escape($sku) . "\',
					upc = \'" . $this->db->escape($upc) . "\',
					ean = \'" . $this->db->escape($ean) . "\',
					jan = \'" . $this->db->escape($jan) . "\',
					isbn = \'" . $this->db->escape($isbn) . "\',
					mpn = \'" . $this->db->escape($mpn) . "\',
					location = \'" . $this->db->escape($location) . "\',
					quantity = \'" . (int)$quantity . "\',
					minimum = \'" . (int)$minimum . "\',
					subtract = \'" . (int)$subtract . "\',
					stock_status_id = \'" . (int)$stock_status_id . "\',
					date_available = \'" . $this->db->escape($date_avail) . "\',
					manufacturer_id = \'" . (int)$manufacturer_id . "\',
					shipping = \'" . (int)$shipping . "\',
					price = \'" . (float)$price . "\',
					points = 0,
					weight = \'" . (float)$weight . "\',
					weight_class_id = \'" . (int)$weight_class_id . "\',
					length = \'" . (float)$length . "\',
					width = \'" . (float)$width . "\',
					height = \'" . (float)$height . "\',
					length_class_id = \'" . (int)$length_class_id . "\',
					status = \'" . (int)$status . "\',
					tax_class_id = \'" . (int)$tax_class_id . "\',
					sort_order = \'" . (int)$sort_order . "\',
					date_added = NOW(),
					date_modified = NOW()";

			if (!empty($image)) {
				$sql .= ", image = \'" . $this->db->escape($image) . "\'";
			}

			$this->db->query($sql);
			$product_id = $this->db->getLastId();
		} else {
			$sql = "UPDATE `" . DB_PREFIX . "product` SET 
					model = \'" . $this->db->escape($model) . "\',
					sku = \'" . $this->db->escape($sku) . "\',
					upc = \'" . $this->db->escape($upc) . "\',
					ean = \'" . $this->db->escape($ean) . "\',
					jan = \'" . $this->db->escape($jan) . "\',
					isbn = \'" . $this->db->escape($isbn) . "\',
					mpn = \'" . $this->db->escape($mpn) . "\',
					location = \'" . $this->db->escape($location) . "\',
					quantity = \'" . (int)$quantity . "\',
					minimum = \'" . (int)$minimum . "\',
					subtract = \'" . (int)$subtract . "\',
					stock_status_id = \'" . (int)$stock_status_id . "\',
					date_available = \'" . $this->db->escape($date_avail) . "\',
					manufacturer_id = \'" . (int)$manufacturer_id . "\',
					shipping = \'" . (int)$shipping . "\',
					price = \'" . (float)$price . "\',
					weight = \'" . (float)$weight . "\',
					weight_class_id = \'" . (int)$weight_class_id . "\',
					length = \'" . (float)$length . "\',
					width = \'" . (float)$width . "\',
					height = \'" . (float)$height . "\',
					length_class_id = \'" . (int)$length_class_id . "\',
					status = \'" . (int)$status . "\',
					tax_class_id = \'" . (int)$tax_class_id . "\',
					sort_order = \'" . (int)$sort_order . "\',
					date_modified = NOW()";

			if (!empty($image)) {
				$sql .= ", image = \'" . $this->db->escape($image) . "\'";
			}

			$sql .= " WHERE product_id = \'" . (int)$product_id . "\'";
			$this->db->query($sql);
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_description` WHERE product_id = \'" . (int)$product_id . "\' AND language_id = \'" . (int)$default_language_id . "\'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET 
						  product_id = \'" . (int)$product_id . "\', 
						  language_id = \'" . (int)$default_language_id . "\', 
						  name = \'" . $this->db->escape($name) . "\', 
						  description = \'" . $this->db->escape($description) . "\', 
						  tag = \'" . $this->db->escape($tags) . "\', 
						  meta_title = \'" . $this->db->escape($meta_title) . "\', 
						  meta_description = \'" . $this->db->escape($meta_desc) . "\', 
						  meta_keyword = \'" . $this->db->escape($meta_keywords) . "\', 
						  diameter = \'" . $this->db->escape($diameter) . "\'");

		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_to_store` SET product_id = \'" . (int)$product_id . "\', store_id = \'" . (int)$default_store_id . "\'");

		$category_ids = [];
		if (!empty($cat_ids_str)) {
			foreach (explode(\';\', $cat_ids_str) as $cid) {
				$cid = (int)trim($cid);
				if ($cid > 0) $category_ids[] = $cid;
			}
		}
		if (!empty($categories_str)) {
			foreach (explode(\';\', $categories_str) as $cpath) {
				$cpath = trim($cpath);
				if (!empty($cpath)) {
					$cid = $this->getCategoryByPath($cpath, 0, $default_store_id, $default_language_id);
					if ($cid > 0) $category_ids[] = $cid;
				}
			}
		}
		$category_ids = array_unique($category_ids);
		if (!empty($category_ids)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = \'" . (int)$product_id . "\'");
			foreach ($category_ids as $cid) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET product_id = \'" . (int)$product_id . "\', category_id = \'" . (int)$cid . "\'");
			}
		}

		if (!empty($extra_imgs_str)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id = \'" . (int)$product_id . "\'");
			$img_order = 1;
			foreach (explode(\';\', $extra_imgs_str) as $img_file) {
				$img_file = trim($img_file);
				if (!empty($img_file)) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET product_id = \'" . (int)$product_id . "\', image = \'" . $this->db->escape($img_file) . "\', sort_order = \'" . (int)$img_order . "\'");
					$img_order++;
				}
			}
		}

		if (!empty($filter_names_str)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_filter` WHERE product_id = \'" . (int)$product_id . "\'");
			foreach (explode(\';\', $filter_names_str) as $f_item) {
				$f_item = trim($f_item);
				if (empty($f_item)) continue;
				if (strpos($f_item, \'=\') !== false) {
					list($fg_name, $rest) = explode(\'=\', $f_item, 2);
					$f_name_parts = explode(\':\', $rest);
					$f_name = $f_name_parts[0];
					$fid = $this->getFilterId($fg_name, $f_name, $default_language_id);
					if ($fid > 0) {
						$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_filter` SET product_id = \'" . (int)$product_id . "\', filter_id = \'" . (int)$fid . "\'");
					}
				}
			}
		}

		if (!empty($specials_str)) {
			if ($this->hasSpecialTable()) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_special` WHERE product_id = \'" . (int)$product_id . "\'");
				foreach (explode(\';\', $specials_str) as $sp_item) {
					$sp_parts = explode(\':\', trim($sp_item));
					if (count($sp_parts) >= 4) {
						$cust_grp_id = (int)$sp_parts[0];
						$sp_start    = trim($sp_parts[1]);
						$sp_end      = trim($sp_parts[2]);
						$sp_price    = (float)$sp_parts[3];
						$this->db->query("INSERT INTO `" . DB_PREFIX . "product_special` SET 
										  product_id = \'" . (int)$product_id . "\', 
										  customer_group_id = \'" . (int)$cust_grp_id . "\', 
										  priority = 1, 
										  price = \'" . (float)$sp_price . "\', 
										  date_start = \'" . $this->db->escape($sp_start) . "\', 
										  date_end = \'" . $this->db->escape($sp_end) . "\'");
					}
				}
			} else {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_discount` WHERE product_id = \'" . (int)$product_id . "\' AND `special` = \'1\'");
				foreach (explode(\';\', $specials_str) as $sp_item) {
					$sp_parts = explode(\':\', trim($sp_item));
					if (count($sp_parts) >= 4) {
						$cust_grp_id = (int)$sp_parts[0];
						$sp_start    = trim($sp_parts[1]);
						$sp_end      = trim($sp_parts[2]);
						$sp_price    = (float)$sp_parts[3];
						$this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET 
										  product_id = \'" . (int)$product_id . "\', 
										  customer_group_id = \'" . (int)$cust_grp_id . "\', 
										  quantity = 1, 
										  priority = 1, 
										  price = \'" . (float)$sp_price . "\', 
										  type = \'F\', 
										  special = 1, 
										  date_start = \'" . $this->db->escape($sp_start) . "\', 
										  date_end = \'" . $this->db->escape($sp_end) . "\'");
					}
				}
			}
		}

		if (!empty($discounts_str)) {
			if ($this->hasSpecialTable()) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_discount` WHERE product_id = \'" . (int)$product_id . "\'");
			} else {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_discount` WHERE product_id = \'" . (int)$product_id . "\' AND `special` = \'0\'");
			}
			foreach (explode(\';\', $discounts_str) as $disc_item) {
				$disc_item = trim($disc_item);
				if (empty($disc_item)) continue;
				$d_parts = explode(\':\', trim($disc_item));
				if (count($d_parts) >= 4) {
					$cust_grp_id = (int)$d_parts[0];
					$qty         = (int)$d_parts[1];
					$priority    = (int)$d_parts[2];
					$rest        = $d_parts[3];
					$d_price     = 0.0;
					$d_start     = \'0000-00-00\';
					$d_end       = \'0000-00-00\';

					if (preg_match(\'/^([\\d.]+)-(\\d{4}-\\d{2}-\\d{2})-(\\d{4}-\\d{2}-\\d{2})$/\', $rest, $m)) {
						$d_price = (float)$m[1];
						$d_start = $m[2];
						$d_end   = $m[3];
					} else {
						$sub_parts = explode(\'-\', $rest);
						if (count($sub_parts) >= 7) {
							$d_price = (float)$sub_parts[0];
							$d_start = $sub_parts[1] . \'-\' . $sub_parts[2] . \'-\' . $sub_parts[3];
							$d_end   = $sub_parts[4] . \'-\' . $sub_parts[5] . \'-\' . $sub_parts[6];
						} elseif (count($sub_parts) >= 3) {
							$d_price = (float)$sub_parts[0];
							$d_start = $sub_parts[1];
							$d_end   = $sub_parts[2];
						} else {
							$d_price = (float)($sub_parts[0] ?? 0);
						}
					}

					if ($this->hasSpecialTable()) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET 
										  product_id = \'" . (int)$product_id . "\', 
										  customer_group_id = \'" . (int)$cust_grp_id . "\', 
										  quantity = \'" . (int)$qty . "\', 
										  priority = \'" . (int)$priority . "\', 
										  price = \'" . (float)$d_price . "\', 
										  date_start = \'" . $this->db->escape($d_start) . "\', 
										  date_end = \'" . $this->db->escape($d_end) . "\'");
					} else {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET 
										  product_id = \'" . (int)$product_id . "\', 
										  customer_group_id = \'" . (int)$cust_grp_id . "\', 
										  quantity = \'" . (int)$qty . "\', 
										  priority = \'" . (int)$priority . "\', 
										  price = \'" . (float)$d_price . "\', 
										  type = \'F\', 
										  special = 0, 
										  date_start = \'" . $this->db->escape($d_start) . "\', 
										  date_end = \'" . $this->db->escape($d_end) . "\'");
					}
				}
			}
		}

		if (!empty($seo_keyword)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = \'product_id\' AND `value` = \'" . (int)$product_id . "\' AND `store_id` = \'" . (int)$default_store_id . "\'");
			$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET 
							  store_id = \'" . (int)$default_store_id . "\', 
							  language_id = \'" . (int)$default_language_id . "\', 
							  `key` = \'product_id\', 
							  `value` = \'" . (int)$product_id . "\', 
							  `keyword` = \'" . $this->db->escape($seo_keyword) . "\'");
		}

		if (!empty($rel_models_str)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_related` WHERE product_id = \'" . (int)$product_id . "\'");
			foreach (explode(\';\', $rel_models_str) as $rel_model) {
				$rel_model = trim($rel_model);
				if (!empty($rel_model)) {
					$rel_id = $this->getProductByModel($rel_model);
					if ($rel_id > 0 && $rel_id != $product_id) {
						$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_related` SET product_id = \'" . (int)$product_id . "\', related_id = \'" . (int)$rel_id . "\'");
					}
				}
			}
		}

		if (!empty($rec_models_str)) {
			$rec_table = $this->getRecommendedTable();
			if ($rec_table) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . $rec_table . "` WHERE product_id = \'" . (int)$product_id . "\'");
				$rec_col = ($rec_table == \'product_recomended\') ? \'recomended_id\' : \'recommended_id\';
				foreach (explode(\';\', $rec_models_str) as $rec_model) {
					$rec_model = trim($rec_model);
					if (!empty($rec_model)) {
						$rec_id = $this->getProductByModel($rec_model);
						if ($rec_id > 0 && $rec_id != $product_id) {
							$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . $rec_table . "` SET product_id = \'" . (int)$product_id . "\', `" . $rec_col . "` = \'" . (int)$rec_id . "\'");
						}
					}
				}
			}
		}

		return [
			\'product_id\' => $product_id,
			\'is_new\'     => $is_new
		];
	}
}');

// TMD Import Controller
ensure_file_written(__DIR__ . '/extension/tmd/admin/controller/other/import.php', '<?php
namespace Opencart\Admin\Controller\Extension\Tmd\Other;

/**
 * TMD Import Module Controller for OpenCart 4
 */
class Import extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/tmd/other/import\');

		$this->document->setTitle($this->language->get(\'heading_title\'));

		$data[\'breadcrumbs\'] = [];

		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/tmd/other/import\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'upload\'] = $this->url->link(\'extension/tmd/other/import.upload\', \'user_token=\' . $this->session->data[\'user_token\']);
		$data[\'export\'] = $this->url->link(\'extension/tmd/other/export\', \'user_token=\' . $this->session->data[\'user_token\']);

		$data[\'user_token\'] = $this->session->data[\'user_token\'];

		$count_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`");
		$data[\'total_products\'] = (int)$count_query->row[\'total\'];

		if (isset($this->session->data[\'success\'])) {
			$data[\'success\'] = $this->session->data[\'success\'];
			unset($this->session->data[\'success\']);
		} else {
			$data[\'success\'] = \'\';
		}

		if (isset($this->session->data[\'error\'])) {
			$data[\'error_warning\'] = $this->session->data[\'error\'];
			unset($this->session->data[\'error\']);
		} else {
			$data[\'error_warning\'] = \'\';
		}

		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/tmd/other/import\', $data));
	}

	public function upload(): void {
		while (ob_get_level()) {
			ob_end_clean();
		}
		ob_start();

		$this->load->language(\'extension/tmd/other/import\');

		$json = [];
		$is_ajax = (!empty($this->request->server[\'HTTP_X_REQUESTED_WITH\']) && strtolower($this->request->server[\'HTTP_X_REQUESTED_WITH\']) == \'xmlhttprequest\') || isset($this->request->get[\'ajax\']);

		try {
			if (!$this->user->hasPermission(\'modify\', \'extension/tmd/other/import\')) {
				$json[\'error\'] = $this->language->get(\'error_permission\');
			}

			if (!$json && (!isset($this->request->files[\'import_file\']) || !is_file($this->request->files[\'import_file\'][\'tmp_name\']))) {
				$json[\'error\'] = $this->language->get(\'error_file\');
			}

			if (!$json) {
				$file_name = strtolower($this->request->files[\'import_file\'][\'name\'] ?? \'\');
				$file_path = $this->request->files[\'import_file\'][\'tmp_name\'];

				$this->load->model(\'extension/tmd/other/import\');

				$language_id = (int)$this->config->get(\'config_language_id\');
				$store_id    = 0;

				$rows = [];

				$is_xlsx = (substr($file_name, -5) === \'.xlsx\');
				if ($is_xlsx || (class_exists(\'ZipArchive\') && $this->isZipFile($file_path))) {
					$rows = $this->parseXlsxFile($file_path);
				} else {
					$content = file_get_contents($file_path);

					if (stripos($content, \'<tr\') !== false) {
						preg_match_all(\'/<tr[^>]*>(.*?)<\/tr>/is\', $content, $tr_matches);
						if (!empty($tr_matches[1])) {
							foreach ($tr_matches[1] as $tr) {
								preg_match_all(\'/<t[dh][^>]*>(.*?)<\/t[dh]>/is\', $tr, $td_matches);
								if (!empty($td_matches[1])) {
									$row = array_map(function($val) {
										return html_entity_decode(strip_tags(trim($val)), ENT_QUOTES, \'UTF-8\');
									}, $td_matches[1]);
									$rows[] = $row;
								}
							}
						}
					} else {
						$handle = fopen($file_path, \'r\');
						if ($handle) {
							$first_line = fgets($handle);
							rewind($handle);

							$delimiter = (substr_count($first_line, "\t") > substr_count($first_line, ",")) ? "\t" : ",";

							while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
								if (!empty($data) && count(array_filter($data)) > 0) {
									$rows[] = $data;
								}
							}
							fclose($handle);
						}
					}
				}

				if (empty($rows)) {
					$json[\'error\'] = \'No valid product rows could be read from the file. Please verify the spreadsheet format.\';
				} else {
					$total_new = 0;
					$total_updated = 0;

					foreach ($rows as $index => $row) {
						$first_val  = trim((string)($row[0] ?? \'\'));
						$fourth_val = trim((string)($row[4] ?? \'\'));

						if ($index === 0 && (stripos($first_val, \'Product\') !== false || stripos($fourth_val, \'Model\') !== false)) {
							continue;
						}

						if (empty($first_val) && empty($fourth_val)) {
							$name_val = trim((string)($row[12] ?? \'\'));
							if (empty($name_val)) continue;
						}

						$res = $this->model_extension_tmd_other_import->importProduct($row, $language_id, $store_id);
						if (!empty($res[\'is_new\'])) {
							$total_new++;
						} else {
							$total_updated++;
						}
					}

					$json[\'success\'] = sprintf(\'Success: Import completed! %d products updated, %d new products added.\', $total_updated, $total_new);
				}
			}
		} catch (\Throwable $e) {
			$json[\'error\'] = \'Import Exception: \' . $e->getMessage();
		}

		while (ob_get_level()) {
			ob_end_clean();
		}

		if ($is_ajax) {
			header(\'Content-Type: application/json; charset=UTF-8\');
			echo json_encode($json);
			exit(0);
		} else {
			if (!empty($json[\'error\'])) {
				$this->session->data[\'error\'] = $json[\'error\'];
			} elseif (!empty($json[\'success\'])) {
				$this->session->data[\'success\'] = $json[\'success\'];
			}
			$this->response->redirect($this->url->link(\'extension/tmd/other/import\', \'user_token=\' . $this->session->data[\'user_token\']));
		}
	}

	private function isZipFile(string $filePath): bool {
		$handle = fopen($filePath, \'r\');
		if (!$handle) return false;
		$bytes = fread($handle, 4);
		fclose($handle);
		return ($bytes === "PK\x03\x04");
	}

	private function colToNum(string $col): int {
		$col = strtoupper($col);
		$num = 0;
		$len = strlen($col);
		for ($i = 0; $i < $len; $i++) {
			$num = $num * 26 + (ord($col[$i]) - ord(\'A\') + 1);
		}
		return $num - 1;
	}

	private function parseXlsxFile(string $filePath): array {
		if (!class_exists(\'ZipArchive\')) {
			return [];
		}

		$zip = new \ZipArchive();
		if ($zip->open($filePath) !== true) {
			return [];
		}

		$sharedStrings = [];
		$ssXml = $zip->getFromName(\'xl/sharedStrings.xml\');
		if ($ssXml !== false) {
			$xml = @simplexml_load_string($ssXml);
			if ($xml && isset($xml->si)) {
				foreach ($xml->si as $si) {
					if (isset($si->t)) {
						$sharedStrings[] = (string)$si->t;
					} elseif (isset($si->r)) {
						$text = \'\';
						foreach ($si->r as $r) {
							$text .= (string)$r->t;
						}
						$sharedStrings[] = $text;
					} else {
						$sharedStrings[] = \'\';
					}
				}
			}
		}

		$sheetXml = $zip->getFromName(\'xl/worksheets/sheet1.xml\');
		if ($sheetXml === false) {
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$name = $zip->getNameIndex($i);
				if (preg_match(\'#xl/worksheets/sheet\d+\.xml#i\', $name)) {
					$sheetXml = $zip->getFromName($name);
					break;
				}
			}
		}
		$zip->close();

		if ($sheetXml === false) {
			return [];
		}

		$rows = [];
		$xml = @simplexml_load_string($sheetXml);
		if ($xml && isset($xml->sheetData->row)) {
			foreach ($xml->sheetData->row as $row) {
				$rowData = [];
				foreach ($row->c as $c) {
					$cellRef = (string)$c[\'r\'];
					preg_match(\'/^([A-Z]+)(\d+)$/i\', $cellRef, $matches);
					if (empty($matches[1])) continue;
					$colIndex = $this->colToNum($matches[1]);

					$cellType = isset($c[\'t\']) ? (string)$c[\'t\'] : \'\';
					$val = \'\';

					if ($cellType === \'s\') {
						$idx = (int)$c->v;
						$val = $sharedStrings[$idx] ?? \'\';
					} elseif ($cellType === \'inlineStr\' && isset($c->is->t)) {
						$val = (string)$c->is->t;
					} elseif (isset($c->v)) {
						$val = (string)$c->v;
					}

					$rowData[$colIndex] = $val;
				}

				if (!empty($rowData)) {
					$maxIndex = max(array_keys($rowData));
					$fullRow = [];
					for ($k = 0; $k <= $maxIndex; $k++) {
						$fullRow[$k] = $rowData[$k] ?? \'\';
					}
					$rows[] = $fullRow;
				}
			}
		}

		return $rows;
	}
}');

// TMD Languages
ensure_file_written(__DIR__ . '/extension/tmd/admin/language/en-gb/other/export.php', '<?php
$_[\'heading_title\']    = \'TMD Export Module\';
$_[\'text_extension\']   = \'Extensions\';
$_[\'text_edit\']        = \'Export Products\';
$_[\'button_export\']    = \'Export Data\';
$_[\'error_permission\'] = \'Warning: You do not have permission to modify TMD Export!\';');

ensure_file_written(__DIR__ . '/extension/tmd/admin/language/en-gb/other/import.php', '<?php
$_[\'heading_title\']       = \'TMD Import Excel File\';
$_[\'text_extension\']      = \'Extensions\';
$_[\'text_success_import\'] = \'Success: %s items imported successfully!\';
$_[\'text_edit\']           = \'Import Excel File\';
$_[\'button_import\']       = \'Import\';
$_[\'error_permission\']    = \'Warning: You do not have permission to modify TMD Import!\';
$_[\'error_file\']          = \'Warning: Invalid or missing file for import!\';');

// TMD Views
ensure_file_written(__DIR__ . '/extension/tmd/admin/view/template/other/export.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-download"></i> {{ text_edit }}</div>
      <div class="card-body">
        <div class="alert alert-info">
          <i class="fa-solid fa-circle-info"></i> Your store currently has <strong>{{ total_products }}</strong> products ready for export.
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="p-3 border rounded bg-light text-center">
              <h5><i class="fa-solid fa-file-excel text-success"></i> Microsoft Excel (.xlsx)</h5>
              <p class="text-muted">Export full catalog with formatted tables, categories, prices, and status.</p>
              <a href="index.php?route=extension/tmd/other/export.download&user_token={{ user_token }}&format=xlsx" class="btn btn-success"><i class="fa-solid fa-download"></i> Export to Excel (.xlsx)</a>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 border rounded bg-light text-center">
              <h5><i class="fa-solid fa-file-csv text-primary"></i> CSV Spreadsheet (.csv)</h5>
              <p class="text-muted">Export standard comma-separated file compatible with all spreadsheet apps.</p>
              <a href="index.php?route=extension/tmd/other/export.download&user_token={{ user_token }}&format=csv" class="btn btn-primary"><i class="fa-solid fa-download"></i> Export to CSV (.csv)</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
{{ footer }}');

ensure_file_written(__DIR__ . '/extension/tmd/admin/view/template/other/import.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    {% if error_warning %}
      <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_warning }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}
    {% if success %}
      <div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> {{ success }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}

    <div class="card">
      <div class="card-header"><i class="fa-solid fa-file-import"></i> {{ text_edit }}</div>
      <div class="card-body">
        <div class="alert alert-info">
          <i class="fa-solid fa-circle-info"></i> Upload your product Excel spreadsheet (<code>.xls</code>, <code>.xlsx</code>, <code>.csv</code>, or <code>.txt</code>). Products will be matched and updated by <strong>Product ID</strong> or <strong>Model</strong>, or newly added if they do not exist.
        </div>

        <div id="alert-zone"></div>

        <form id="form-import" action="index.php?route=extension/tmd/other/import.upload&user_token={{ user_token }}" method="post" enctype="multipart/form-data">
          <div class="row mb-4">
            <label for="input-file" class="col-sm-3 col-form-label fw-bold">Select File for Import</label>
            <div class="col-sm-9">
              <input type="file" name="import_file" id="input-file" class="form-control" accept=".xls,.xlsx,.csv,.txt,.tsv" required />
              <div class="form-text">Supports standard TMD format with all 57 columns (Price, Quantity, Categories, Diameter, Attributes, Filters, Specials, Images, etc.)</div>
            </div>
          </div>
          <div class="text-end">
            <a href="{{ export }}" class="btn btn-outline-secondary me-2"><i class="fa-solid fa-download"></i> Go to Export</a>
            <button type="submit" id="button-upload" class="btn btn-primary"><i class="fa-solid fa-upload"></i> {{ button_import }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript"><!--
$(\'#form-import\').on(\'submit\', function(e) {
    var fileInput = $(\'#input-file\')[0];
    if (!fileInput.files || !fileInput.files.length) {
        alert(\'Please select a file to import!\');
        return false;
    }

    e.preventDefault();
    var formData = new FormData(this);

    $.ajax({
        url: \'index.php?route=extension/tmd/other/import.upload&user_token={{ user_token }}&ajax=1\',
        type: \'post\',
        data: formData,
        dataType: \'text\',
        cache: false,
        contentType: false,
        processData: false,
        beforeSend: function() {
            $(\'#button-upload\').prop(\'disabled\', true).html(\'<i class="fa-solid fa-spinner fa-spin"></i> Importing...\');
            $(\'#alert-zone\').html(\'\');
        },
        complete: function() {
            $(\'#button-upload\').prop(\'disabled\', false).html(\'<i class="fa-solid fa-upload"></i> {{ button_import }}\');
        },
        success: function(raw) {
            var json = {};
            try {
                json = JSON.parse(raw);
            } catch (err) {
                $(\'#alert-zone\').html(\'<div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> Server Response: \' + raw + \' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>\');
                return;
            }

            if (json[\'error\']) {
                $(\'#alert-zone\').html(\'<div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> \' + json[\'error\'] + \' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>\');
            }
            if (json[\'success\']) {
                $(\'#alert-zone\').html(\'<div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> \' + json[\'success\'] + \' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>\');
                $(\'#form-import\')[0].reset();
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            var msg = xhr.responseText || thrownError;
            $(\'#alert-zone\').html(\'<div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> Import Error: \' + msg + \'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>\');
        }
    });
});
//--></script>
{{ footer }}');

// HuntBee install.json
ensure_file_written(__DIR__ . '/extension/huntbee/install.json', json_encode([
    'name' => 'HuntBee OpenCart Extensions',
    'version' => '3.2.3',
    'author' => 'HuntBee OpenCart Services',
    'link' => 'https://www.huntbee.com'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// HuntBee BasePlugin Controller
ensure_file_written(__DIR__ . '/extension/huntbee/admin/controller/module/base_plugin.php', '<?php
namespace Opencart\Admin\Controller\Extension\Huntbee\Module;

class BasePlugin extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/huntbee/module/base_plugin\');
		$this->document->setTitle($this->language->get(\'heading_title\'));

		$data[\'breadcrumbs\'] = [];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_extension\'),
			\'href\' => $this->url->link(\'marketplace/extension\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&type=module\')
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/huntbee/module/base_plugin\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'save\'] = $this->url->link(\'extension/huntbee/module/base_plugin.save\', \'user_token=\' . $this->session->data[\'user_token\']);
		$data[\'back\'] = $this->url->link(\'marketplace/extension\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&type=module\');
		$data[\'user_token\'] = $this->session->data[\'user_token\'];
		$data[\'extension_version\'] = \'3.0.0\';

		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/huntbee/module/base_plugin\', $data));
	}

	public function save(): void {
		$this->load->language(\'extension/huntbee/module/base_plugin\');
		$json = [];
		if (!$this->user->hasPermission(\'modify\', \'extension/huntbee/module/base_plugin\')) {
			$json[\'error\'][\'warning\'] = $this->language->get(\'error_permission\');
		}
		if (!$json) {
			$this->load->model(\'setting/setting\');
			$this->model_setting_setting->editSetting(\'module_base_plugin\', $this->request->post);
			$json[\'success\'] = $this->language->get(\'text_success\');
		}
		$this->response->addHeader(\'Content-Type: application/json\');
		$this->response->setOutput(json_encode($json));
	}
}');

// HuntBee BasePlugin Language & View
ensure_file_written(__DIR__ . '/extension/huntbee/admin/language/en-gb/module/base_plugin.php', '<?php
$_[\'heading_title\']    = \'Base Plugin from HuntBee (3xxx)\';
$_[\'text_extension\']   = \'Extensions\';
$_[\'text_success\']     = \'Success: You have modified HuntBee Base Plugin!\';
$_[\'text_edit\']        = \'Edit Base Plugin from HuntBee\';
$_[\'error_permission\'] = \'Warning: You do not have permission to modify HuntBee Base Plugin!\';');

ensure_file_written(__DIR__ . '/extension/huntbee/admin/view/template/module/base_plugin.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-module" data-bs-toggle="tooltip" title="{{ button_save }}" class="btn btn-primary"><i class="fa-solid fa-save"></i></button>
        <a href="{{ back }}" data-bs-toggle="tooltip" title="{{ button_back }}" class="btn btn-light"><i class="fa-solid fa-reply"></i></a>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-pencil"></i> {{ text_edit }}</div>
      <div class="card-body">
        <form id="form-module" action="{{ save }}" method="post" data-oc-toggle="ajax">
          <div class="alert alert-info">
            <i class="fa-solid fa-info-circle"></i> HuntBee Base Plugin v{{ extension_version }} loaded successfully for OpenCart 4 compatibility.
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}');

// HuntBee HbCart Controller
ensure_file_written(__DIR__ . '/extension/huntbee/admin/controller/module/hb_cart.php', '<?php
namespace Opencart\Admin\Controller\Extension\Huntbee\Module;

class HbCart extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/huntbee/module/hb_cart\');
		$this->document->setTitle($this->language->get(\'heading_title\'));

		$data[\'breadcrumbs\'] = [];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_extension\'),
			\'href\' => $this->url->link(\'marketplace/extension\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&type=module\')
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/huntbee/module/hb_cart\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'save\'] = $this->url->link(\'extension/huntbee/module/hb_cart.save\', \'user_token=\' . $this->session->data[\'user_token\']);
		$data[\'back\'] = $this->url->link(\'marketplace/extension\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&type=module\');
		$data[\'user_token\'] = $this->session->data[\'user_token\'];
		$data[\'extension_version\'] = \'3.1.5\';

		$data[\'module_hb_cart_status\'] = $this->config->get(\'module_hb_cart_status\');
		$data[\'module_hb_cart_patch_status\'] = $this->config->get(\'module_hb_cart_patch_status\');

		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/huntbee/module/hb_cart\', $data));
	}

	public function save(): void {
		$this->load->language(\'extension/huntbee/module/hb_cart\');
		$json = [];
		if (!$this->user->hasPermission(\'modify\', \'extension/huntbee/module/hb_cart\')) {
			$json[\'error\'][\'warning\'] = $this->language->get(\'error_permission\');
		}
		if (!$json) {
			$this->load->model(\'setting/setting\');
			$this->model_setting_setting->editSetting(\'module_hb_cart\', $this->request->post);
			$json[\'success\'] = $this->language->get(\'text_success\');
		}
		$this->response->addHeader(\'Content-Type: application/json\');
		$this->response->setOutput(json_encode($json));
	}
}');

// HuntBee HbCart Language & View
ensure_file_written(__DIR__ . '/extension/huntbee/admin/language/en-gb/module/hb_cart.php', '<?php
$_[\'heading_title\']    = \'Abandoned Cart Email & Patch (3xxx)\';
$_[\'text_extension\']   = \'Extensions\';
$_[\'text_success\']     = \'Success: You have modified Abandoned Cart Email module settings!\';
$_[\'text_edit\']        = \'Edit Abandoned Cart Email Module\';
$_[\'text_enabled\']     = \'Enabled\';
$_[\'text_disabled\']    = \'Disabled\';
$_[\'entry_status\']     = \'Module Status\';
$_[\'entry_patch_status\'] = \'MarketinSG Quick Checkout Patch Status\';
$_[\'error_permission\'] = \'Warning: You do not have permission to modify Abandoned Cart Email!\';');

ensure_file_written(__DIR__ . '/extension/huntbee/admin/view/template/module/hb_cart.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-module" data-bs-toggle="tooltip" title="{{ button_save }}" class="btn btn-primary"><i class="fa-solid fa-save"></i></button>
        <a href="{{ back }}" data-bs-toggle="tooltip" title="{{ button_back }}" class="btn btn-light"><i class="fa-solid fa-reply"></i></a>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-pencil"></i> {{ text_edit }}</div>
      <div class="card-body">
        <form id="form-module" action="{{ save }}" method="post" data-oc-toggle="ajax">
          <div class="row mb-3">
            <label for="input-status" class="col-sm-3 col-form-label">{{ entry_status }}</label>
            <div class="col-sm-9">
              <select name="module_hb_cart_status" id="input-status" class="form-select">
                <option value="1"{% if module_hb_cart_status %} selected{% endif %}>{{ text_enabled }}</option>
                <option value="0"{% if not module_hb_cart_status %} selected{% endif %}>{{ text_disabled }}</option>
              </select>
            </div>
          </div>
          <div class="row mb-3">
            <label for="input-patch-status" class="col-sm-3 col-form-label">{{ entry_patch_status }}</label>
            <div class="col-sm-9">
              <select name="module_hb_cart_patch_status" id="input-patch-status" class="form-select">
                <option value="1"{% if module_hb_cart_patch_status %} selected{% endif %}>{{ text_enabled }}</option>
                <option value="0"{% if not module_hb_cart_patch_status %} selected{% endif %}>{{ text_disabled }}</option>
              </select>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}');

// HuntBee OrderReview Controller
ensure_file_written(__DIR__ . '/extension/huntbee/admin/controller/module/order_review.php', '<?php
namespace Opencart\Admin\Controller\Extension\Huntbee\Module;

class OrderReview extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/huntbee/module/order_review\');
		$this->document->setTitle($this->language->get(\'heading_title\'));

		$data[\'breadcrumbs\'] = [];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_extension\'),
			\'href\' => $this->url->link(\'marketplace/extension\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&type=module\')
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/huntbee/module/order_review\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'save\'] = $this->url->link(\'extension/huntbee/module/order_review.save\', \'user_token=\' . $this->session->data[\'user_token\']);
		$data[\'back\'] = $this->url->link(\'marketplace/extension\', \'user_token=\' . $this->session->data[\'user_token\'] . \'&type=module\');
		$data[\'user_token\'] = $this->session->data[\'user_token\'];
		$data[\'extension_version\'] = \'3.2.3\';

		$data[\'module_order_review_status\'] = $this->config->get(\'module_order_review_status\');

		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/huntbee/module/order_review\', $data));
	}

	public function save(): void {
		$this->load->language(\'extension/huntbee/module/order_review\');
		$json = [];
		if (!$this->user->hasPermission(\'modify\', \'extension/huntbee/module/order_review\')) {
			$json[\'error\'][\'warning\'] = $this->language->get(\'error_permission\');
		}
		if (!$json) {
			$this->load->model(\'setting/setting\');
			$this->model_setting_setting->editSetting(\'module_order_review\', $this->request->post);
			$json[\'success\'] = $this->language->get(\'text_success\');
		}
		$this->response->addHeader(\'Content-Type: application/json\');
		$this->response->setOutput(json_encode($json));
	}
}');

// HuntBee OrderReview Language & View
ensure_file_written(__DIR__ . '/extension/huntbee/admin/language/en-gb/module/order_review.php', '<?php
$_[\'heading_title\']    = \'FeedbackFlow: Post-Purchase Review Invitation\';
$_[\'text_extension\']   = \'Extensions\';
$_[\'text_success\']     = \'Success: You have modified FeedbackFlow module settings!\';
$_[\'text_edit\']        = \'Edit FeedbackFlow: Post-Purchase Review Invitation\';
$_[\'text_enabled\']     = \'Enabled\';
$_[\'text_disabled\']    = \'Disabled\';
$_[\'entry_status\']     = \'Module Status\';
$_[\'error_permission\'] = \'Warning: You do not have permission to modify FeedbackFlow!\';');

ensure_file_written(__DIR__ . '/extension/huntbee/admin/view/template/module/order_review.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-module" data-bs-toggle="tooltip" title="{{ button_save }}" class="btn btn-primary"><i class="fa-solid fa-save"></i></button>
        <a href="{{ back }}" data-bs-toggle="tooltip" title="{{ button_back }}" class="btn btn-light"><i class="fa-solid fa-reply"></i></a>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-star"></i> {{ heading_title }}</div>
      <div class="card-body">
        <form id="form-module" action="{{ action }}" method="post">
          <div class="row mb-3">
            <label class="col-sm-2 col-form-label">{{ entry_status }}</label>
            <div class="col-sm-10">
              <select name="module_order_review_status" class="form-select">
                <option value="1"{% if module_order_review_status %} selected{% endif %}>{{ text_enabled }}</option>
                <option value="0"{% if not module_order_review_status %} selected{% endif %}>{{ text_disabled }}</option>
              </select>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}');

// 9b. Deploy MCE Shipping CSV Import Extension Files
ensure_file_written(__DIR__ . '/extension/opencart/admin/controller/other/arameximport.php', '<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Other;

class Arameximport extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/opencart/other/arameximport\');
		$this->document->setTitle($this->language->get(\'heading_title\'));
		$this->load->model(\'extension/opencart/other/arameximport\');

		if ($this->request->server[\'REQUEST_METHOD\'] == \'POST\' && $this->validate()) {
			if (isset($this->request->files[\'import\'][\'tmp_name\']) && is_uploaded_file($this->request->files[\'import\'][\'tmp_name\'])) {
				$file = $this->request->files[\'import\'][\'tmp_name\'];
				$handle = fopen($file, \'r\');

				if ($handle !== false) {
					$this->model_extension_opencart_other_arameximport->clearCountries();
					$row_index = 0;
					$total_imported = 0;

					while (($data = fgetcsv($handle, 5000, \',\')) !== false) {
						if (empty($data) || count(array_filter($data, \'strlen\')) === 0) {
							continue;
						}

						$first_val = trim((string)($data[0] ?? \'\'));
						$second_val = trim((string)($data[1] ?? \'\'));

						if ($row_index === 0 && (!is_numeric($first_val) || stripos($second_val, \'country\') !== false || stripos($first_val, \'sn\') !== false)) {
							$row_index++;
							continue;
						}

						$country_data = [
							\'id\'              => (int)$first_val,
							\'country\'         => trim((string)($data[1] ?? \'\')),
							\'aramex_zone_pri\' => trim((string)($data[2] ?? \'\')),
							\'fedex_zone_eco\'  => trim((string)($data[3] ?? \'\')),
							\'fedex_zone_pri\'  => trim((string)($data[4] ?? ($data[2] ?? \'\')))
						];

						if (!empty($country_data[\'country\'])) {
							$this->model_extension_opencart_other_arameximport->addCountry($country_data);
							$total_imported++;
						}

						$row_index++;
					}

					fclose($handle);
					$this->session->data[\'success\'] = sprintf(\'Success: %d country zone records imported successfully!\', $total_imported);
					$this->response->redirect($this->url->link(\'extension/opencart/other/arameximport\', \'user_token=\' . $this->session->data[\'user_token\']));
				} else {
					$this->error[\'warning\'] = $this->language->get(\'error_file\');
				}
			} else {
				$this->error[\'warning\'] = $this->language->get(\'error_empty\');
			}
		}

		$data[\'heading_title\'] = $this->language->get(\'heading_title\');
		$data[\'button_import\'] = $this->language->get(\'button_import\');
		$data[\'text_edit\']     = $this->language->get(\'text_edit\');

		$sample_url = HTTP_CATALOG . \'image/catalog/postage_country_sample.csv\';
		$data[\'entry_import\'] = sprintf($this->language->get(\'entry_import\'), $sample_url);

		if (isset($this->session->data[\'error\'])) {
			$data[\'error_warning\'] = $this->session->data[\'error\'];
			unset($this->session->data[\'error\']);
		} elseif (isset($this->error[\'warning\'])) {
			$data[\'error_warning\'] = $this->error[\'warning\'];
		} else {
			$data[\'error_warning\'] = \'\';
		}

		if (isset($this->session->data[\'success\'])) {
			$data[\'success\'] = $this->session->data[\'success\'];
			unset($this->session->data[\'success\']);
		} else {
			$data[\'success\'] = \'\';
		}

		$data[\'breadcrumbs\'] = [];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/opencart/other/arameximport\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'import\'] = $this->url->link(\'extension/opencart/other/arameximport\', \'user_token=\' . $this->session->data[\'user_token\']);
		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/opencart/other/arameximport\', $data));
	}

	protected function validate(): bool {
		if (!$this->user->hasPermission(\'modify\', \'extension/opencart/other/arameximport\')) {
			$this->error[\'warning\'] = $this->language->get(\'error_permission\');
		}
		return !$this->error;
	}
}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/model/other/arameximport.php', '<?php
namespace Opencart\Admin\Model\Extension\Opencart\Other;

class Arameximport extends \Opencart\System\Engine\Model {
	public function clearCountries(): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "postage_country_time`");
	}

	public function addCountry(array $data): void {
		$id = isset($data[\'id\']) ? (int)$data[\'id\'] : 0;
		$country = isset($data[\'country\']) ? trim((string)$data[\'country\']) : \'\';
		$aramex_zone_pri = isset($data[\'aramex_zone_pri\']) ? strtolower(trim((string)$data[\'aramex_zone_pri\'])) : \'\';
		$fedex_zone_pri = isset($data[\'fedex_zone_pri\']) && $data[\'fedex_zone_pri\'] !== \'\' ? strtolower(trim((string)$data[\'fedex_zone_pri\'])) : $aramex_zone_pri;
		$fedex_zone_eco = isset($data[\'fedex_zone_eco\']) ? strtolower(trim((string)$data[\'fedex_zone_eco\'])) : \'\';

		if ($country !== \'\') {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "postage_country_time` SET 
				`id` = \'" . $id . "\',
				`country` = \'" . $this->db->escape($country) . "\',
				`aramex_zone_pri` = \'" . $this->db->escape($aramex_zone_pri) . "\',
				`fedex_zone_pri` = \'" . $this->db->escape($fedex_zone_pri) . "\',
				`fedex_zone_eco` = \'" . $this->db->escape($fedex_zone_eco) . "\'
			");
		}
	}
}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/controller/other/aramexratesimport.php', '<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Other;

class Aramexratesimport extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/opencart/other/aramexratesimport\');
		$this->document->setTitle($this->language->get(\'heading_title\'));
		$this->load->model(\'extension/opencart/other/aramexratesimport\');

		if ($this->request->server[\'REQUEST_METHOD\'] == \'POST\' && $this->validate()) {
			if (isset($this->request->files[\'import\'][\'tmp_name\']) && is_uploaded_file($this->request->files[\'import\'][\'tmp_name\'])) {
				$file = $this->request->files[\'import\'][\'tmp_name\'];
				$handle = fopen($file, \'r\');

				if ($handle !== false) {
					$this->model_extension_opencart_other_aramexratesimport->clearRates();
					$row_index = 0;
					$total_imported = 0;

					while (($data = fgetcsv($handle, 5000, \',\')) !== false) {
						if (empty($data) || count(array_filter($data, \'strlen\')) === 0) {
							continue;
						}

						$first_val = trim((string)($data[0] ?? \'\'));
						$second_val = trim((string)($data[1] ?? \'\'));

						if ($row_index === 0 && (!is_numeric($first_val) || stripos($first_val, \'id\') !== false || stripos($second_val, \'kg\') !== false)) {
							$row_index++;
							continue;
						}

						$rate_data = [
							\'id\'   => (int)$first_val,
							\'kg\'   => (float)$second_val,
							\'a\'    => $data[2] ?? 0,
							\'b\'    => $data[3] ?? 0,
							\'c\'    => $data[4] ?? 0,
							\'d\'    => $data[5] ?? 0,
							\'e\'    => $data[6] ?? 0,
							\'f\'    => $data[7] ?? 0,
							\'g\'    => $data[8] ?? 0,
							\'h\'    => $data[9] ?? 0,
							\'i\'    => $data[10] ?? 0,
							\'j\'    => $data[11] ?? 0,
							\'k\'    => $data[12] ?? 0,
							\'type\' => !empty($data[13]) ? trim((string)$data[13]) : \'priority\'
						];

						if (!empty($first_val) || $rate_data[\'kg\'] > 0) {
							$this->model_extension_opencart_other_aramexratesimport->addRate($rate_data);
							$total_imported++;
						}

						$row_index++;
					}

					fclose($handle);
					$this->session->data[\'success\'] = sprintf(\'Success: %d MCE priority rate records imported successfully!\', $total_imported);
					$this->response->redirect($this->url->link(\'extension/opencart/other/aramexratesimport\', \'user_token=\' . $this->session->data[\'user_token\']));
				} else {
					$this->error[\'warning\'] = $this->language->get(\'error_file\');
				}
			} else {
				$this->error[\'warning\'] = $this->language->get(\'error_empty\');
			}
		}

		$data[\'heading_title\'] = $this->language->get(\'heading_title\');
		$data[\'button_import\'] = $this->language->get(\'button_import\');
		$data[\'text_edit\']     = $this->language->get(\'text_edit\');

		$sample_url = HTTP_CATALOG . \'image/catalog/mce-priorityrate-sample.csv\';
		$data[\'entry_import\'] = sprintf($this->language->get(\'entry_import\'), $sample_url);

		if (isset($this->session->data[\'error\'])) {
			$data[\'error_warning\'] = $this->session->data[\'error\'];
			unset($this->session->data[\'error\']);
		} elseif (isset($this->error[\'warning\'])) {
			$data[\'error_warning\'] = $this->error[\'warning\'];
		} else {
			$data[\'error_warning\'] = \'\';
		}

		if (isset($this->session->data[\'success\'])) {
			$data[\'success\'] = $this->session->data[\'success\'];
			unset($this->session->data[\'success\']);
		} else {
			$data[\'success\'] = \'\';
		}

		$data[\'breadcrumbs\'] = [];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/opencart/other/aramexratesimport\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'import\'] = $this->url->link(\'extension/opencart/other/aramexratesimport\', \'user_token=\' . $this->session->data[\'user_token\']);
		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/opencart/other/aramexratesimport\', $data));
	}

	protected function validate(): bool {
		if (!$this->user->hasPermission(\'modify\', \'extension/opencart/other/aramexratesimport\')) {
			$this->error[\'warning\'] = $this->language->get(\'error_permission\');
		}
		return !$this->error;
	}
}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/model/other/aramexratesimport.php', '<?php
namespace Opencart\Admin\Model\Extension\Opencart\Other;

class Aramexratesimport extends \Opencart\System\Engine\Model {
	public function clearRates(): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "aramex_rates`");
	}

	public function addRate(array $data): void {
		$id = isset($data[\'id\']) ? (int)$data[\'id\'] : 0;
		$kg = isset($data[\'kg\']) ? (float)$data[\'kg\'] : 0.0;
		$type = !empty($data[\'type\']) ? trim((string)$data[\'type\']) : \'priority\';

		$zones = [\'a\', \'b\', \'c\', \'d\', \'e\', \'f\', \'g\', \'h\', \'i\', \'j\', \'k\'];
		$zone_sql = [];
		foreach ($zones as $zone) {
			$val = isset($data[$zone]) ? (float)str_replace(\',\', \'\', (string)$data[$zone]) : 0.0;
			$zone_sql[] = "`" . $zone . "` = \'" . $val . "\'";
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "aramex_rates` SET 
			`id` = \'" . $id . "\',
			`kg` = \'" . $kg . "\',
			" . implode(", ", $zone_sql) . ",
			`type` = \'" . $this->db->escape($type) . "\'
		");
	}
}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/controller/other/fedexratesimport.php', '<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Other;

class Fedexratesimport extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language(\'extension/opencart/other/fedexratesimport\');
		$this->document->setTitle($this->language->get(\'heading_title\'));
		$this->load->model(\'extension/opencart/other/fedexratesimport\');

		if ($this->request->server[\'REQUEST_METHOD\'] == \'POST\' && $this->validate()) {
			if (isset($this->request->files[\'import\'][\'tmp_name\']) && is_uploaded_file($this->request->files[\'import\'][\'tmp_name\'])) {
				$file = $this->request->files[\'import\'][\'tmp_name\'];
				$handle = fopen($file, \'r\');

				if ($handle !== false) {
					$this->model_extension_opencart_other_fedexratesimport->clearRates();
					$row_index = 0;
					$total_imported = 0;

					while (($data = fgetcsv($handle, 5000, \',\')) !== false) {
						if (empty($data) || count(array_filter($data, \'strlen\')) === 0) {
							continue;
						}

						$first_val = trim((string)($data[0] ?? \'\'));
						$second_val = trim((string)($data[1] ?? \'\'));

						if ($row_index === 0 && (!is_numeric($first_val) || stripos($first_val, \'id\') !== false || stripos($second_val, \'kg\') !== false)) {
							$row_index++;
							continue;
						}

						$rate_data = [
							\'id\'   => (int)$first_val,
							\'kg\'   => (float)$second_val,
							\'a\'    => $data[2] ?? 0,
							\'b\'    => $data[3] ?? 0,
							\'c\'    => $data[4] ?? 0,
							\'d\'    => $data[5] ?? 0,
							\'e\'    => $data[6] ?? 0,
							\'f\'    => $data[7] ?? 0,
							\'g\'    => $data[8] ?? 0,
							\'h\'    => $data[9] ?? 0,
							\'i\'    => $data[10] ?? 0,
							\'j\'    => $data[11] ?? 0,
							\'k\'    => $data[12] ?? 0,
							\'type\' => !empty($data[13]) ? trim((string)$data[13]) : \'economy\'
						];

						if (!empty($first_val) || $rate_data[\'kg\'] > 0) {
							$this->model_extension_opencart_other_fedexratesimport->addRate($rate_data);
							$total_imported++;
						}

						$row_index++;
					}

					fclose($handle);
					$this->session->data[\'success\'] = sprintf(\'Success: %d MCE economy rate records imported successfully!\', $total_imported);
					$this->response->redirect($this->url->link(\'extension/opencart/other/fedexratesimport\', \'user_token=\' . $this->session->data[\'user_token\']));
				} else {
					$this->error[\'warning\'] = $this->language->get(\'error_file\');
				}
			} else {
				$this->error[\'warning\'] = $this->language->get(\'error_empty\');
			}
		}

		$data[\'heading_title\'] = $this->language->get(\'heading_title\');
		$data[\'button_import\'] = $this->language->get(\'button_import\');
		$data[\'text_edit\']     = $this->language->get(\'text_edit\');

		$sample_url = HTTP_CATALOG . \'image/catalog/mce-economyrate-sample.csv\';
		$data[\'entry_import\'] = sprintf($this->language->get(\'entry_import\'), $sample_url);

		if (isset($this->session->data[\'error\'])) {
			$data[\'error_warning\'] = $this->session->data[\'error\'];
			unset($this->session->data[\'error\']);
		} elseif (isset($this->error[\'warning\'])) {
			$data[\'error_warning\'] = $this->error[\'warning\'];
		} else {
			$data[\'error_warning\'] = \'\';
		}

		if (isset($this->session->data[\'success\'])) {
			$data[\'success\'] = $this->session->data[\'success\'];
			unset($this->session->data[\'success\']);
		} else {
			$data[\'success\'] = \'\';
		}

		$data[\'breadcrumbs\'] = [];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'text_home\'),
			\'href\' => $this->url->link(\'common/dashboard\', \'user_token=\' . $this->session->data[\'user_token\'])
		];
		$data[\'breadcrumbs\'][] = [
			\'text\' => $this->language->get(\'heading_title\'),
			\'href\' => $this->url->link(\'extension/opencart/other/fedexratesimport\', \'user_token=\' . $this->session->data[\'user_token\'])
		];

		$data[\'import\'] = $this->url->link(\'extension/opencart/other/fedexratesimport\', \'user_token=\' . $this->session->data[\'user_token\']);
		$data[\'header\'] = $this->load->controller(\'common/header\');
		$data[\'column_left\'] = $this->load->controller(\'common/column_left\');
		$data[\'footer\'] = $this->load->controller(\'common/footer\');

		$this->response->setOutput($this->load->view(\'extension/opencart/other/fedexratesimport\', $data));
	}

	protected function validate(): bool {
		if (!$this->user->hasPermission(\'modify\', \'extension/opencart/other/fedexratesimport\')) {
			$this->error[\'warning\'] = $this->language->get(\'error_permission\');
		}
		return !$this->error;
	}
}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/model/other/fedexratesimport.php', '<?php
namespace Opencart\Admin\Model\Extension\Opencart\Other;

class Fedexratesimport extends \Opencart\System\Engine\Model {
	public function clearRates(): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "fedex_rates`");
	}

	public function addRate(array $data): void {
		$id = isset($data[\'id\']) ? (int)$data[\'id\'] : 0;
		$kg = isset($data[\'kg\']) ? (float)$data[\'kg\'] : 0.0;
		$type = !empty($data[\'type\']) ? trim((string)$data[\'type\']) : \'economy\';

		$zones = [\'a\', \'b\', \'c\', \'d\', \'e\', \'f\', \'g\', \'h\', \'i\', \'j\', \'k\'];
		$zone_sql = [];
		foreach ($zones as $zone) {
			$val = isset($data[$zone]) ? (float)str_replace(\',\', \'\', (string)$data[$zone]) : 0.0;
			$zone_sql[] = "`" . $zone . "` = \'" . $val . "\'";
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "fedex_rates` SET 
			`id` = \'" . $id . "\',
			`kg` = \'" . $kg . "\',
			" . implode(", ", $zone_sql) . ",
			`type` = \'" . $this->db->escape($type) . "\'
		");
	}
}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/language/en-gb/other/arameximport.php', '<?php
namespace Opencart\Admin\Language\EnGb\Extension\Opencart\Other;

$_[\'heading_title\']    = \'MCE Country Import\';
$_[\'text_extension\']   = \'Extensions\';
$_[\'text_success\']     = \'Success: MCE Country mappings have been successfully updated!\';
$_[\'text_edit\']        = \'MCE Country Import\';
$_[\'entry_import\']     = \'MCE Country Import CSV File: Please import the same format. If you do not have the format, please download <a href="%s" target="_blank" class="fw-bold">From here</a>.\';
$_[\'button_import\']    = \'MCE Country Import\';
$_[\'error_permission\'] = \'Warning: You do not have permission to modify MCE Country Import!\';
$_[\'error_file\']       = \'Warning: Please upload a valid CSV file!\';
$_[\'error_empty\']      = \'Warning: The uploaded file was empty or contains no valid rows!\';');

ensure_file_written(__DIR__ . '/extension/opencart/admin/language/en-gb/other/aramexratesimport.php', '<?php
namespace Opencart\Admin\Language\EnGb\Extension\Opencart\Other;

$_[\'heading_title\']    = \'MCE Priority Import\';
$_[\'text_extension\']   = \'Extensions\';
$_[\'text_success\']     = \'Success: MCE Priority Rates have been successfully updated!\';
$_[\'text_edit\']        = \'MCE Priority Import\';
$_[\'entry_import\']     = \'MCE Priority Import CSV File: Please import the same format. If you do not have the format, please download <a href="%s" target="_blank" class="fw-bold">From here</a>.\';
$_[\'button_import\']    = \'MCE Priority Import\';
$_[\'error_permission\'] = \'Warning: You do not have permission to modify MCE Priority Import!\';
$_[\'error_file\']       = \'Warning: Please upload a valid CSV file!\';
$_[\'error_empty\']      = \'Warning: The uploaded file was empty or contains no valid rows!\';');

ensure_file_written(__DIR__ . '/extension/opencart/admin/language/en-gb/other/fedexratesimport.php', '<?php
namespace Opencart\Admin\Language\EnGb\Extension\Opencart\Other;

$_[\'heading_title\']    = \'MCE Economy Import\';
$_[\'text_extension\']   = \'Extensions\';
$_[\'text_success\']     = \'Success: MCE Economy Rates have been successfully updated!\';
$_[\'text_edit\']        = \'MCE Economy Import\';
$_[\'entry_import\']     = \'MCE Economy Import CSV File: Please import the same format. If you do not have the format, please download <a href="%s" target="_blank" class="fw-bold">From here</a>.\';
$_[\'button_import\']    = \'MCE Economy Import\';
$_[\'error_permission\'] = \'Warning: You do not have permission to modify MCE Economy Import!\';
$_[\'error_file\']       = \'Warning: Please upload a valid CSV file!\';
$_[\'error_empty\']      = \'Warning: The uploaded file was empty or contains no valid rows!\';');

ensure_file_written(__DIR__ . '/extension/opencart/admin/view/template/other/arameximport.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-import" class="btn btn-primary"><i class="fa-solid fa-upload"></i> {{ button_import }}</button>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    {% if error_warning %}
      <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_warning }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}
    {% if success %}
      <div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> {{ success }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}

    <div class="card">
      <div class="card-header"><i class="fa-solid fa-pencil"></i> {{ text_edit }}</div>
      <div class="card-body">
        <form id="form-import" action="{{ import }}" method="post" enctype="multipart/form-data">
          <input type="hidden" name="format" value="csv" />
          <div class="row mb-3">
            <label class="col-sm-4 col-form-label">{{ entry_import|raw }}</label>
            <div class="col-sm-8">
              <input type="file" name="import" class="form-control" accept=".csv,text/csv" required />
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/view/template/other/aramexratesimport.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-import" class="btn btn-primary"><i class="fa-solid fa-upload"></i> {{ button_import }}</button>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    {% if error_warning %}
      <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_warning }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}
    {% if success %}
      <div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> {{ success }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}

    <div class="card">
      <div class="card-header"><i class="fa-solid fa-pencil"></i> {{ text_edit }}</div>
      <div class="card-body">
        <form id="form-import" action="{{ import }}" method="post" enctype="multipart/form-data">
          <input type="hidden" name="format" value="csv" />
          <div class="row mb-3">
            <label class="col-sm-4 col-form-label">{{ entry_import|raw }}</label>
            <div class="col-sm-8">
              <input type="file" name="import" class="form-control" accept=".csv,text/csv" required />
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}');

ensure_file_written(__DIR__ . '/extension/opencart/admin/view/template/other/fedexratesimport.twig', '{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-import" class="btn btn-primary"><i class="fa-solid fa-upload"></i> {{ button_import }}</button>
      </div>
      <h1>{{ heading_title }}</h1>
      <ol class="breadcrumb">
        {% for breadcrumb in breadcrumbs %}
          <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
        {% endfor %}
      </ol>
    </div>
  </div>
  <div class="container-fluid">
    {% if error_warning %}
      <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_warning }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}
    {% if success %}
      <div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> {{ success }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    {% endif %}

    <div class="card">
      <div class="card-header"><i class="fa-solid fa-pencil"></i> {{ text_edit }}</div>
      <div class="card-body">
        <form id="form-import" action="{{ import }}" method="post" enctype="multipart/form-data">
          <input type="hidden" name="format" value="csv" />
          <div class="row mb-3">
            <label class="col-sm-4 col-form-label">{{ entry_import|raw }}</label>
            <div class="col-sm-8">
              <input type="file" name="import" class="form-control" accept=".csv,text/csv" required />
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}');

// 10. Patch column_left.php on Server for Navigation Menus
$admin_dirs = ['msbadmin', 'admin'];
foreach ($admin_dirs as $adir) {
    // Patch language
    $col_lang_file = __DIR__ . '/' . $adir . '/language/en-gb/common/column_left.php';
    if (file_exists($col_lang_file)) {
        $col_lang_content = file_get_contents($col_lang_file);
        if (strpos($col_lang_content, 'text_arameximport') === false) {
            $lang_keys = "\n\$_['text_arameximport']        = 'MCE Country Import';\n\$_['text_arameximportrates']   = 'MCE Priority Import';\n\$_['text_fedeximportrates']    = 'MCE Economy Import';\n";
            $col_lang_content = str_replace('<?php', '<?php' . $lang_keys, $col_lang_content);
            file_put_contents($col_lang_file, $col_lang_content);
            echo "✔ Patched {$adir}/language/en-gb/common/column_left.php for MCE shipping imports.<br/>";
        }
    }

    // Patch controller
    $col_left_file = __DIR__ . '/' . $adir . '/controller/common/column_left.php';
    if (file_exists($col_left_file)) {
        $col_content = file_get_contents($col_left_file);
        
        // TMD navigation patch
        if (strpos($col_content, 'extension/tmd/other/import') === false) {
            $search_tmd = '$maintenance[] = [
					\'name\'     => $this->language->get(\'text_log\'),
					\'href\'     => $this->url->link(\'tool/log\', \'user_token=\' . $this->session->data[\'user_token\']),
					\'children\' => []
				];';
            $replace_tmd = '$maintenance[] = [
					\'name\'     => $this->language->get(\'text_log\'),
					\'href\'     => $this->url->link(\'tool/log\', \'user_token=\' . $this->session->data[\'user_token\']),
					\'children\' => []
				];

			if ($this->user->hasPermission(\'access\', \'extension/tmd/other/import\')) {
				$maintenance[] = [
					\'name\'     => \'TMD Import Excel\',
					\'href\'     => $this->url->link(\'extension/tmd/other/import\', \'user_token=\' . $this->session->data[\'user_token\']),
					\'children\' => []
				];
			}

			if ($this->user->hasPermission(\'access\', \'extension/tmd/other/export\')) {
				$maintenance[] = [
					\'name\'     => \'TMD Export Excel\',
					\'href\'     => $this->url->link(\'extension/tmd/other/export\', \'user_token=\' . $this->session->data[\'user_token\']),
					\'children\' => []
				];
			}';
            if (strpos($col_content, $search_tmd) !== false) {
                $col_content = str_replace($search_tmd, $replace_tmd, $col_content);
                file_put_contents($col_left_file, $col_content);
                echo "✔ Patched {$adir}/controller/common/column_left.php for TMD navigation.<br/>";
            }
        }

        // MCE Shipping Import navigation patch
        if (strpos($col_content, 'extension/opencart/other/arameximport') === false) {
            $search_mce = "if (\$user) {
				\$system[] = [
					'name'     => \$this->language->get('text_users'),
					'href'     => '',
					'children' => \$user
				];
			}";
            $replace_mce = "if (\$user) {
				\$system[] = [
					'name'     => \$this->language->get('text_users'),
					'href'     => '',
					'children' => \$user
				];
			}

			if (\$this->user->hasPermission('access', 'extension/opencart/other/arameximport')) {
				\$system[] = [
					'name'     => \$this->language->get('text_arameximport'),
					'href'     => \$this->url->link('extension/opencart/other/arameximport', 'user_token=' . \$this->session->data['user_token']),
					'children' => []
				];
			}

			if (\$this->user->hasPermission('access', 'extension/opencart/other/aramexratesimport')) {
				\$system[] = [
					'name'     => \$this->language->get('text_arameximportrates'),
					'href'     => \$this->url->link('extension/opencart/other/aramexratesimport', 'user_token=' . \$this->session->data['user_token']),
					'children' => []
				];
			}

			if (\$this->user->hasPermission('access', 'extension/opencart/other/fedexratesimport')) {
				\$system[] = [
					'name'     => \$this->language->get('text_fedeximportrates'),
					'href'     => \$this->url->link('extension/opencart/other/fedexratesimport', 'user_token=' . \$this->session->data['user_token']),
					'children' => []
				];
			}";
            if (strpos($col_content, $search_mce) !== false) {
                $col_content = str_replace($search_mce, $replace_mce, $col_content);
                file_put_contents($col_left_file, $col_content);
                echo "✔ Patched {$adir}/controller/common/column_left.php for MCE shipping import navigation.<br/>";
            }
        }
    }
}
echo "✔ Deployed all TMD, HuntBee, and MCE Shipping Import extension files to filesystem.<br/>";

echo "<h2>2. Clearing Storage Cache...</h2>";
$cache_dir = defined('DIR_STORAGE') ? DIR_STORAGE . 'cache/' : DIR_SYSTEM . 'storage/cache/';

function clear_dir($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), array('.', '..'));
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            clear_dir($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

if (is_dir($cache_dir)) {
    clear_dir($cache_dir);
    echo "✔ Cache cleared successfully at: " . htmlspecialchars($cache_dir) . "<br/>";
} else {
    echo "Cache directory not found.<br/>";
}

if (function_exists('opcache_reset')) {
    @opcache_reset();
    echo "✔ PHP OPcache reset successfully.<br/>";
}

echo "<h2>3. Checking MCE Shipping Database Tables...</h2>";
$tables = ['postage_country_time', 'fedex_rates', 'aramex_rates'];
foreach ($tables as $t) {
    $t_name = $prefix . $t;
    $res = mysqli_query($link, "SHOW TABLES LIKE '{$t_name}'");
    if ($res && mysqli_num_rows($res) > 0) {
        $count_res = mysqli_query($link, "SELECT COUNT(*) as cnt FROM {$t_name}");
        $cnt_row = mysqli_fetch_assoc($count_res);
        echo "✔ Table <b>{$t_name}</b> exists with <b>{$cnt_row['cnt']}</b> rows.<br/>";
    } else {
        echo "<span style='color:red;'>✘ Table <b>{$t_name}</b> IS MISSING ON SERVER DATABASE!</span><br/>";
    }
}

echo "<h2>4. Testing Shipping Quote Calculation for UK (country_id = 222)...</h2>";
$lang_id_res = mysqli_query($link, "SELECT value FROM {$prefix}setting WHERE `key` = 'config_language_id'");
$lang_row = mysqli_fetch_assoc($lang_id_res);
$lang_id = $lang_row ? (int)$lang_row['value'] : 1;
echo "Active language_id: <b>{$lang_id}</b><br/>";

$c_res = mysqli_query($link, "SELECT name FROM {$prefix}country_description WHERE country_id = 222 AND language_id = '{$lang_id}'");
if ($c_row = mysqli_fetch_assoc($c_res)) {
    $cname = $c_row['name'];
    echo "Country Name in country_description: <b>{$cname}</b><br/>";
    
    $pct_res = mysqli_query($link, "SELECT * FROM {$prefix}postage_country_time WHERE country = '" . mysqli_real_escape_string($link, $cname) . "'");
    if ($pct_row = mysqli_fetch_assoc($pct_res)) {
        echo "✔ Found match in postage_country_time! fedex_zone_eco: <b>" . ($pct_row['fedex_zone_eco'] ?: 'NULL') . "</b>, fedex_zone_pri: <b>" . ($pct_row['fedex_zone_pri'] ?: 'NULL') . "</b>, aramex_zone_pri: <b>" . ($pct_row['aramex_zone_pri'] ?: 'NULL') . "</b><br/>";
        
        $zone = $pct_row['fedex_zone_eco'];
        if ($zone) {
            $rate_res = mysqli_query($link, "SELECT `{$zone}` FROM {$prefix}fedex_rates WHERE kg = 0.5 AND type = 'economy'");
            if ($r_row = mysqli_fetch_assoc($rate_res)) {
                echo "✔ Calculated Economy Rate for 0.5kg (Zone {$zone}): <b>{$r_row[$zone]}</b> USD<br/>";
            } else {
                echo "<span style='color:red;'>✘ Rate query returned NO rows for 0.5kg in fedex_rates!</span><br/>";
            }
        }
    } else {
        echo "<span style='color:red;'>✘ Country '{$cname}' NOT FOUND in postage_country_time table!</span><br/>";
    }
} else {
    echo "<span style='color:red;'>✘ Country ID 222 NOT FOUND in country_description for language_id {$lang_id}!</span><br/>";
}

echo "<h2>5. Checking Shipping Methods Configuration & File Existence...</h2>";
$methods = ['flat', 'pickup', 'weight', 'pri', 'eco', 'aramexpri'];
foreach ($methods as $m) {
    $st_res = mysqli_query($link, "SELECT value FROM {$prefix}setting WHERE `key` = 'shipping_{$m}_status'");
    $st_row = mysqli_fetch_assoc($st_res);
    $status_val = $st_row ? $st_row['value'] : 'MISSING';
    echo "Shipping '<b>{$m}</b>' status in setting table: <b>{$status_val}</b><br/>";

    $file_path = __DIR__ . "/extension/opencart/catalog/model/shipping/{$m}.php";
    if (file_exists($file_path)) {
        echo "✔ File exists: <code>extension/opencart/catalog/model/shipping/{$m}.php</code><br/>";
    } else {
        echo "<span style='color:red;'>✘ FILE MISSING: extension/opencart/catalog/model/shipping/{$m}.php</span><br/>";
    }
}

echo "<h2>6. Updating Supercheckout Available Shipping Settings in Database...</h2>";
$sc_res = mysqli_query($link, "SELECT setting_id, value FROM {$prefix}setting WHERE `key` = 'supercheckout'");
if ($sc_row = mysqli_fetch_assoc($sc_res)) {
    $sc_data = json_decode($sc_row['value'], true);
    
    $all_methods = ['flat', 'pickup', 'weight', 'pri', 'eco', 'aramexpri'];
    $payments = ['bank_transfer', 'cod', 'free_checkout', 'hbl', 'mastercard', 'pp_standard', 'stripe'];
    
    foreach ($all_methods as $m) {
        if (!isset($sc_data['step']['shipping_method']['available'][$m])) {
            $sc_data['step']['shipping_method']['available'][$m] = $payments;
        }
    }
    
    $updated_val = mysqli_real_escape_string($link, json_encode($sc_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    mysqli_query($link, "UPDATE {$prefix}setting SET value = '{$updated_val}' WHERE setting_id = '{$sc_row['setting_id']}'");
    echo "✔ Updated Supercheckout available shipping methods in database!<br/>";
    echo "<pre>Supercheckout Available Shipping Keys:\n";
    print_r(array_keys($sc_data['step']['shipping_method']['available']));
    echo "</pre>";
} else {
    echo "<span style='color:red;'>✘ Supercheckout settings NOT found in setting table!</span><br/>";
}

echo "<h2>6b. Syncing Bank Transfer Setting Keys in Database...</h2>";
$res_bt1 = mysqli_query($link, "SELECT value FROM {$prefix}setting WHERE `key` = 'payment_bank_transfer_bank1' OR `key` = 'payment_bank_transfer_bank_1'");
if ($r1 = mysqli_fetch_assoc($res_bt1)) {
    $clean_v1 = preg_replace("/(\r?\n){3,}/", "\n\n", $r1['value']);
    $v1 = mysqli_real_escape_string($link, $clean_v1);
    mysqli_query($link, "INSERT INTO {$prefix}setting (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'payment_bank_transfer', 'payment_bank_transfer_bank_1', '{$v1}', 0) ON DUPLICATE KEY UPDATE `value`='{$v1}'");
    mysqli_query($link, "UPDATE {$prefix}setting SET `value`='{$v1}' WHERE `key`='payment_bank_transfer_bank1'");
    echo "✔ Cleaned and Synced payment_bank_transfer_bank_1<br/>";
}
$res_bt2 = mysqli_query($link, "SELECT value FROM {$prefix}setting WHERE `key` = 'payment_bank_transfer_bank2' OR `key` = 'payment_bank_transfer_bank_2'");
if ($r2 = mysqli_fetch_assoc($res_bt2)) {
    $clean_v2 = preg_replace("/(\r?\n){3,}/", "\n\n", $r2['value']);
    $v2 = mysqli_real_escape_string($link, $clean_v2);
    mysqli_query($link, "INSERT INTO {$prefix}setting (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'payment_bank_transfer', 'payment_bank_transfer_bank_2', '{$v2}', 0) ON DUPLICATE KEY UPDATE `value`='{$v2}'");
    mysqli_query($link, "UPDATE {$prefix}setting SET `value`='{$v2}' WHERE `key`='payment_bank_transfer_bank2'");
    echo "✔ Cleaned and Synced payment_bank_transfer_bank_2<br/>";
}

echo "<h2>7. Testing 6.5kg Priority & Economy Rates for Zone F...</h2>";
$res_eco_test = mysqli_query($link, "SELECT `f` FROM {$prefix}fedex_rates WHERE kg = 6.5 AND type = 'economy'");
if ($r = mysqli_fetch_assoc($res_eco_test)) {
    echo "✔ FedEx Economy Rate for 6.5kg (Zone f): <b>{$r['f']}</b> USD<br/>";
} else {
    echo "<span style='color:red;'>✘ Economy Rate returned 0 rows for 6.5kg in fedex_rates!</span><br/>";
}

$res_pri_test = mysqli_query($link, "SELECT `f` FROM {$prefix}fedex_rates WHERE kg = 6.5 AND type = 'priority'");
if ($r = mysqli_fetch_assoc($res_pri_test)) {
    echo "✔ FedEx Priority Rate for 6.5kg (Zone f): <b>{$r['f']}</b> USD<br/>";
} else {
    echo "<span style='color:red;'>✘ Priority Rate returned 0 rows for 6.5kg in fedex_rates!</span><br/>";
}

$res_ara_test = mysqli_query($link, "SELECT `f` FROM {$prefix}aramex_rates WHERE kg = 6.5 AND type = 'priority'");
if ($r = mysqli_fetch_assoc($res_ara_test)) {
    echo "✔ Aramex Priority Rate for 6.5kg (Zone f): <b>{$r['f']}</b> USD<br/>";
} else {
    echo "<span style='color:red;'>✘ Aramex Priority Rate returned 0 rows for 6.5kg in aramex_rates!</span><br/>";
}

echo "<h2>8. Updating Megamenu & Header Theme Settings...</h2>";

// Update Megamenu Module 61 to show Home icon
$res_mm = mysqli_query($link, "SELECT setting FROM {$prefix}module WHERE module_id = 61");
if ($row_mm = mysqli_fetch_assoc($res_mm)) {
    $mm_setting = json_decode($row_mm['setting'], true);
    $mm_setting['home_item'] = 'icon';
    $new_mm_setting = mysqli_real_escape_string($link, json_encode($mm_setting));
    mysqli_query($link, "UPDATE {$prefix}module SET setting = '{$new_mm_setting}' WHERE module_id = 61");
    echo "✔ Megamenu (Module 61) home_item updated to 'icon'.<br/>";
}

// Update soconfig welcome message & contact numbers (Track Your Order link & Hotline)
$res_so = mysqli_query($link, "SELECT value FROM {$prefix}soconfig WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
if ($row_so = mysqli_fetch_assoc($res_so)) {
    $gen = json_decode($row_so['value'], true);
    $new_msg = '<b>Welcome to Magical Singing Bowls</b> | Authentic Himalayan Singing Bowls, Gongs &amp; Sound Healing Instruments';
    $gen['welcome_message'] = array(
        '1' => $new_msg,
        '2' => $new_msg
    );
    $new_contact = '<ul><li><a href="index.php?route=account/order&amp;language=en-gb"><i class="fa fa-truck"></i>Track Your Order</a></li><li><a href="tel:+9779851051290"><i class="fa fa-phone-square"></i>Hotline +977 9851051290</a></li></ul>';
    $gen['contact_number'] = array(
        '1' => $new_contact,
        '2' => $new_contact
    );
    $val = mysqli_real_escape_string($link, json_encode($gen));
    mysqli_query($link, "UPDATE {$prefix}soconfig SET value = '{$val}' WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
    echo "✔ soconfig welcome message & Track Your Order links updated.<br/>";
}

// Update Page Builder Module 33 (Home 1) to remove armchair / vertical menu gap and start Latest Products at the top
$clean_pb = '[{"text_class_id":"row_i4fp","text_class":"content-main-w","cols":[{"text_class_id":"col_left","text_class":"main-left","lg_col":3,"md_col":3,"sm_col":12,"xs_col":12,"widgets":[{"name":"Home 1 - Col Latest products","module":"so_theme.so_extra_slider.42","type":"module"},{"name":"Home 1 - Latest Blogs sidebar","module":"so_theme.so_latest_blog.103","type":"module"},{"name":"Home 1 - Col Top Rate","module":"so_theme.so_extra_slider.105","type":"module"}]},{"text_class_id":"col_right","text_class":"main-right","lg_col":9,"md_col":9,"sm_col":12,"xs_col":12,"widgets":[{"name":"Home 1 - Slideshow","module":"so_theme.so_home_slider.34","type":"module"},{"name":"Home layout 1 - Trending items","module":"so_theme.so_listing_tabs.69","type":"module"},{"name":"Home layout 1 - Listing Tabs custom New items","module":"so_theme.so_listing_tabs.70","type":"module"}]}]},{"text_class_id":"row_bottom","text_class":"container","cols":[{"text_class_id":"col_bottom","text_class":"col-style","lg_col":12,"md_col":12,"sm_col":12,"xs_col":12,"widgets":[{"name":"Home 1 - Most Viewed","module":"so_theme.so_extra_slider.106","type":"module"}]}]}]';

$res_m33 = mysqli_query($link, "SELECT setting FROM {$prefix}module WHERE module_id = 33");
if ($row_m33 = mysqli_fetch_assoc($res_m33)) {
    $s33 = json_decode($row_m33['setting'], true);
    $s33['page_builder'] = $clean_pb;
    $new_s33 = mysqli_real_escape_string($link, json_encode($s33));
    mysqli_query($link, "UPDATE {$prefix}module SET setting = '{$new_s33}' WHERE module_id = 33");
    echo "✔ Page Builder Home 1 (Module 33) layout updated (Latest products positioned at top).<br/>";
}

// Clear template cache
$cache_dir = __DIR__ . '/system/storage/cache/template/';
if (is_dir($cache_dir)) {
    $files = glob($cache_dir . '*');
    foreach ($files as $f) {
        if (is_file($f)) @unlink($f);
    }
    echo "✔ Twig template cache cleared.<br/>";
}

echo "<h2>Done! Remote database updated and cache cleared.</h2>";

