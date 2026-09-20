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

// 6b. Ensure Custom Product Columns (sound_embed, lookbook_id, diameter, variant, override)
function update_server_add_col($link, $table, $col, $def) {
    $q = mysqli_query($link, "SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    if ($q && mysqli_num_rows($q) == 0) {
        mysqli_query($link, "ALTER TABLE `{$table}` ADD `{$col}` {$def}");
        echo "✔ Added column <b>{$col}</b> to <b>{$table}</b>.<br/>";
    }
}
update_server_add_col($link, "{$prefix}product", "sound_embed", "TEXT NULL");
update_server_add_col($link, "{$prefix}product", "lookbook_id", "INT(11) NOT NULL DEFAULT 0");
update_server_add_col($link, "{$prefix}product", "variant", "TEXT NULL");
update_server_add_col($link, "{$prefix}product", "override", "TEXT NULL");
update_server_add_col($link, "{$prefix}product_description", "diameter", "TEXT NULL");

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


// 8b. Deploy precompiled admin bootstrap.css if missing or broken
$b64_bootstrap_css = "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";
$admin_css_dirs = [__DIR__ . "/msbadmin/view/stylesheet", __DIR__ . "/admin/view/stylesheet"];
foreach ($admin_css_dirs as $acd) {
    if (is_dir($acd)) {
        $target_css = $acd . "/bootstrap.css";
        if (!file_exists($target_css) || filesize($target_css) < 1000) {
            ensure_file_written($target_css, gzuncompress(base64_decode($b64_bootstrap_css)));
            echo "✔ Deployed admin bootstrap.css to " . basename(dirname(dirname($acd))) . ".<br/>";
        }
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

// TMD Export Controller (Latest Full Version with Filters & Custom Fields)
ensure_file_written(__DIR__ . '/extension/tmd/admin/controller/other/export.php', gzuncompress(base64_decode('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')));

// TMD Import Model// TMD Import Model (with sound_embed support)
ensure_file_written(__DIR__ . '/extension/tmd/admin/model/other/import.php', gzuncompress(base64_decode('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')));

// TMD Import Controller// TMD Import Controller
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
ensure_file_written(__DIR__ . '/extension/tmd/admin/language/en-gb/other/export.php', file_get_contents(__DIR__ . '/extension/tmd/admin/language/en-gb/other/export.php'));

ensure_file_written(__DIR__ . '/extension/tmd/admin/language/en-gb/other/import.php', '<?php
$_[\'heading_title\']       = \'TMD Import Excel File\';
$_[\'text_extension\']      = \'Extensions\';
$_[\'text_success_import\'] = \'Success: %s items imported successfully!\';
$_[\'text_edit\']           = \'Import Excel File\';
$_[\'button_import\']       = \'Import\';
$_[\'error_permission\']    = \'Warning: You do not have permission to modify TMD Import!\';
$_[\'error_file\']          = \'Warning: Invalid or missing file for import!\';');

// TMD Views
ensure_file_written(__DIR__ . '/extension/tmd/admin/view/template/other/export.twig', gzuncompress(base64_decode('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')));

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

// Clean broken custom_url demo links from SO modules (e.g. 42, 69, 70, 105)
$res_custom_mods = mysqli_query($link, "SELECT module_id, setting FROM {$prefix}module WHERE setting LIKE '%custom%'");
$cleaned_count = 0;
while ($r_mod = mysqli_fetch_assoc($res_custom_mods)) {
    $s_mod = json_decode($r_mod['setting'], true);
    if (is_array($s_mod)) {
        $changed_mod = false;
        if (!empty($s_mod['custom_url']) && strpos($s_mod['custom_url'], 'custom') !== false) {
            $s_mod['custom_url'] = '';
            $changed_mod = true;
        }
        if (isset($s_mod['module_description']) && is_array($s_mod['module_description'])) {
            foreach ($s_mod['module_description'] as $lid => $mdesc) {
                if (!empty($mdesc['custom_url']) && strpos($mdesc['custom_url'], 'custom') !== false) {
                    $s_mod['module_description'][$lid]['custom_url'] = '';
                    $changed_mod = true;
                }
            }
        }
        if ($changed_mod) {
            $new_setting_mod = mysqli_real_escape_string($link, json_encode($s_mod));
            mysqli_query($link, "UPDATE {$prefix}module SET setting = '{$new_setting_mod}' WHERE module_id = " . (int)$r_mod['module_id']);
            $cleaned_count++;
        }
    }
}
echo "✔ Cleaned broken custom_url links from {$cleaned_count} modules.<br/>";

// Update Nepalese Rupee (NPR) conversion rate in oc_currency
$npr_rate = 152.27457744;
mysqli_query($link, "UPDATE {$prefix}currency SET value = '{$npr_rate}', status = 1, date_modified = NOW() WHERE code = 'NPR'");
echo "✔ Updated Nepalese Rupee (NPR) exchange rate to {$npr_rate}.<br/>";

// Section 9. Layout Decoupling & Header/Footer Locking for Magical Singing Bowls
echo "<h2>9. Applying Layout Decoupling & Header/Footer Locking...</h2>";

// 9.1 Database soconfig settings
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

// 9.2 Populate 0-byte layout1 CSS files
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

// 9.3 Deploy updated soconfig.twig
ensure_file_written(__DIR__ . '/extension/so_theme/admin/view/template/soconfig/soconfig.twig', file_get_contents(__DIR__ . '/extension/so_theme/admin/view/template/soconfig/soconfig.twig'));
echo "✔ Deployed updated soconfig.twig to server.<br/>";

// 9.4 Deploy updated admin soconfig.php
ensure_file_written(__DIR__ . '/extension/so_theme/admin/controller/module/soconfig.php', file_get_contents(__DIR__ . '/extension/so_theme/admin/controller/module/soconfig.php'));
echo "✔ Deployed updated admin soconfig.php to server.<br/>";

// 9.5 Deploy updated class/soconfig.php
ensure_file_written(__DIR__ . '/extension/so_theme/admin/view/template/soconfig/class/soconfig.php', file_get_contents(__DIR__ . '/extension/so_theme/admin/view/template/soconfig/class/soconfig.php'));
echo "✔ Deployed updated class/soconfig.php to server.<br/>";

// 9.6 Deploy updated header.twig and footer.twig
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/view/template/common/header.twig', file_get_contents(__DIR__ . '/extension/so_theme/catalog/view/template/common/header.twig'));
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/view/template/common/footer.twig', file_get_contents(__DIR__ . '/extension/so_theme/catalog/view/template/common/footer.twig'));
echo "✔ Deployed updated header.twig and footer.twig to server.<br/>";

// 9.7 Deploy updated event so_soconfig.php
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/controller/event/so_soconfig.php', file_get_contents(__DIR__ . '/extension/so_theme/catalog/controller/event/so_soconfig.php'));
echo "✔ Deployed updated so_soconfig.php event controller to server.<br/>";

// 9.8 Deploy updated so_basic_products layout_default.twig
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/view/template/module/so_basic_products/layout_default.twig', file_get_contents(__DIR__ . '/extension/so_theme/catalog/view/template/module/so_basic_products/layout_default.twig'));
echo "✔ Deployed updated so_basic_products layout_default.twig to server.<br/>";

// 9.9 Deploy updated admin so_basic_products.php
ensure_file_written(__DIR__ . '/extension/so_theme/admin/controller/module/so_basic_products.php', file_get_contents(__DIR__ . '/extension/so_theme/admin/controller/module/so_basic_products.php'));
echo "✔ Deployed updated admin so_basic_products.php to server.<br/>";

// 9.10 Register all 39 Home Page Builder modules in oc_layout_module for Home layout (layout_id = 1)
$home_modules_map = [
    1 => 33, 2 => 38, 3 => 76, 4 => 121, 5 => 122, 6 => 141, 7 => 150, 8 => 159, 9 => 167, 10 => 168,
    11 => 185, 12 => 199, 13 => 203, 14 => 212, 15 => 217, 16 => 231, 17 => 242, 18 => 254, 19 => 263, 20 => 272,
    21 => 433, 22 => 434, 23 => 435, 24 => 436, 25 => 437, 26 => 439, 27 => 440, 28 => 441, 29 => 442, 30 => 443,
    31 => 444, 32 => 445, 33 => 446, 34 => 447, 35 => 449, 36 => 470, 37 => 488, 38 => 502, 39 => 515
];

foreach ($home_modules_map as $sort_num => $m_id) {
    $code_val = "so_theme.so_page_builder.{$m_id}";
    $chk_hm = mysqli_query($link, "SELECT * FROM `{$prefix}layout_module` WHERE layout_id = 1 AND position = 'content_home' AND code = '{$code_val}'");
    if (mysqli_num_rows($chk_hm) == 0) {
        mysqli_query($link, "INSERT INTO `{$prefix}layout_module` (layout_id, code, position, sort_order) VALUES (1, '{$code_val}', 'content_home', {$sort_num})");
    } else {
        mysqli_query($link, "UPDATE `{$prefix}layout_module` SET sort_order = {$sort_num} WHERE layout_id = 1 AND position = 'content_home' AND code = '{$code_val}'");
    }
}
echo "✔ All 39 Home Page Builder layout modules registered in content_home.<br/>";

// 9.11 Patch all 18 Cache_Lite Lite.php files on server filesystem
$lite_files = glob(__DIR__ . '/extension/so_theme/system/library/so/*/Cache_Lite/Lite.php');
foreach ($lite_files as $lf) {
    $lf_content = file_get_contents($lf);
    if (strpos($lf_content, 'class_alias') === false) {
        $alias_code = "\n\nif (!class_exists('Cache_Lite')) {\n    class_alias('Opencart\\Catalog\\Controller\\Extension\\SoTheme\\Module\\Cache_Lite', 'Cache_Lite');\n}\n";
        file_put_contents($lf, $lf_content . $alias_code);
        echo "✔ Added class_alias to " . basename(dirname(dirname($lf))) . " Cache_Lite/Lite.php.<br/>";
    }
}

// Clear template cache and minify CSS cache
$cache_dirs_purge = [
    __DIR__ . '/storage/cache/template/',
    __DIR__ . '/system/storage/cache/template/',
    __DIR__ . '/extension/so_theme/catalog/view/template/minify/'
];
foreach ($cache_dirs_purge as $cdp) {
    if (is_dir($cdp)) {
        $items_p = glob($cdp . '*');
        foreach ($items_p as $ip) {
            if (is_file($ip)) @unlink($ip);
        }
    }
}
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
echo "✔ All template and minify CSS caches purged.<br/>";

echo "<h2>Done! Remote database updated and cache cleared.</h2>";

