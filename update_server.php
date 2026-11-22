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
// 9.12 Fix SEO URL for Category 294 (Carving Singing Bowls)
$check_cat294 = mysqli_query($link, "SELECT * FROM `{$prefix}seo_url` WHERE `key` = 'path' AND `value` = '294'");
$updated_cat294 = false;
while ($row_c294 = mysqli_fetch_assoc($check_cat294)) {
    echo "Found SEO URL for Category 294: ID " . $row_c294['seo_url_id'] . " with keyword '" . htmlspecialchars($row_c294['keyword']) . "'<br/>";
    if ($row_c294['keyword'] !== 'nepali-handmade-carving-singing-bowls') {
        mysqli_query($link, "UPDATE `{$prefix}seo_url` SET `keyword` = 'nepali-handmade-carving-singing-bowls' WHERE `seo_url_id` = " . (int)$row_c294['seo_url_id']);
        echo "✔ Updated Category 294 SEO URL to 'nepali-handmade-carving-singing-bowls'.<br/>";
        $updated_cat294 = true;
    }
}
if (!$updated_cat294) {
    echo "✔ Category 294 SEO URL is already up to date.<br/>";
}

// 9.12b Fix SEO URL for Category 301 (Planetary, Therapeutic, Harmonic Sound - Information)
$target_c301_keyword = 'planetary-therapeutic-harmonic-sound-information';
$col_query = mysqli_query($link, "SHOW COLUMNS FROM `{$prefix}seo_url`");
$seo_cols = [];
if ($col_query) {
    while ($c = mysqli_fetch_assoc($col_query)) {
        $seo_cols[] = $c['Field'];
    }
}
$has_key_col = in_array('key', $seo_cols);
$has_query_col = in_array('query', $seo_cols);

if ($has_key_col) {
    $check_c301 = mysqli_query($link, "SELECT * FROM `{$prefix}seo_url` WHERE `key` = 'path' AND `value` = '301'");
    if (mysqli_num_rows($check_c301) == 0) {
        $insert_sql = "INSERT INTO `{$prefix}seo_url` (store_id, language_id, `key`, `value`, `keyword`" . (in_array('sort_order', $seo_cols) ? ", `sort_order`" : "") . (in_array('query', $seo_cols) ? ", `query`" : "") . ") VALUES (0, 1, 'path', '301', '{$target_c301_keyword}'" . (in_array('sort_order', $seo_cols) ? ", 0" : "") . (in_array('query', $seo_cols) ? ", 'category_id=301'" : "") . ")";
        mysqli_query($link, $insert_sql);
        echo "✔ Inserted Category 301 SEO URL '{$target_c301_keyword}' (key/value).<br/>";
    } else {
        while ($r301 = mysqli_fetch_assoc($check_c301)) {
            echo "Found SEO URL for Category 301: ID " . $r301['seo_url_id'] . " with keyword '" . htmlspecialchars($r301['keyword']) . "'<br/>";
            if (empty($r301['keyword']) || $r301['keyword'] === 'Himalayan-Singing-Bowls' || $r301['keyword'] !== $target_c301_keyword) {
                mysqli_query($link, "UPDATE `{$prefix}seo_url` SET `keyword` = '{$target_c301_keyword}' WHERE `seo_url_id` = " . (int)$r301['seo_url_id']);
                echo "✔ Updated Category 301 SEO URL to '{$target_c301_keyword}'.<br/>";
            } else {
                echo "✔ Category 301 SEO URL is already up to date.<br/>";
            }
        }
    }
}
if ($has_query_col) {
    $check_q301 = mysqli_query($link, "SELECT * FROM `{$prefix}seo_url` WHERE `query` = 'category_id=301'");
    if (mysqli_num_rows($check_q301) == 0) {
        $insert_q = "INSERT INTO `{$prefix}seo_url` (store_id, language_id, `query`, `keyword`" . ($has_key_col ? ", `key`, `value`" : "") . (in_array('sort_order', $seo_cols) ? ", `sort_order`" : "") . ") VALUES (0, 1, 'category_id=301', '{$target_c301_keyword}'" . ($has_key_col ? ", 'path', '301'" : "") . (in_array('sort_order', $seo_cols) ? ", 0" : "") . ")";
        mysqli_query($link, $insert_q);
        echo "✔ Inserted Category 301 SEO URL '{$target_c301_keyword}' (query).<br/>";
    } else {
        while ($rq301 = mysqli_fetch_assoc($check_q301)) {
            if (empty($rq301['keyword']) || $rq301['keyword'] === 'Himalayan-Singing-Bowls' || $rq301['keyword'] !== $target_c301_keyword) {
                mysqli_query($link, "UPDATE `{$prefix}seo_url` SET `keyword` = '{$target_c301_keyword}' WHERE `seo_url_id` = " . (int)$rq301['seo_url_id']);
                echo "✔ Updated Category 301 query SEO URL to '{$target_c301_keyword}'.<br/>";
            }
        }
    }
}

// 9.13 Deploy updated catalog/controller/startup/seo_url.php, category.php, and column_left.php
ensure_file_written(__DIR__ . '/catalog/controller/startup/seo_url.php', file_get_contents(__DIR__ . '/catalog/controller/startup/seo_url.php'));
ensure_file_written(__DIR__ . '/catalog/controller/product/category.php', file_get_contents(__DIR__ . '/catalog/controller/product/category.php'));
ensure_file_written(__DIR__ . '/catalog/controller/common/column_left.php', file_get_contents(__DIR__ . '/catalog/controller/common/column_left.php'));
echo "✔ Deployed updated startup/seo_url.php, category.php, and column_left.php to server.<br/>";

// 9.14 Deploy updated admin models (information, category, product) and developer controller with automated Varnish cache purge
ensure_file_written(__DIR__ . '/msbadmin/model/catalog/information.php', file_get_contents(__DIR__ . '/msbadmin/model/catalog/information.php'));
ensure_file_written(__DIR__ . '/msbadmin/model/catalog/category.php', file_get_contents(__DIR__ . '/msbadmin/model/catalog/category.php'));
ensure_file_written(__DIR__ . '/msbadmin/model/catalog/product.php', file_get_contents(__DIR__ . '/msbadmin/model/catalog/product.php'));
ensure_file_written(__DIR__ . '/msbadmin/controller/common/developer.php', file_get_contents(__DIR__ . '/msbadmin/controller/common/developer.php'));
ensure_file_written(__DIR__ . '/extension/tmd/admin/controller/other/import.php', file_get_contents(__DIR__ . '/extension/tmd/admin/controller/other/import.php'));
echo "✔ Deployed updated information, category, product, developer, and TMD import controllers with auto Varnish purge.<br/>";

// 9.15 Deploy updated account/login, account/register, startup/session, startup/customer, and so_soconfig
ensure_file_written(__DIR__ . '/catalog/controller/account/login.php', file_get_contents(__DIR__ . '/catalog/controller/account/login.php'));
ensure_file_written(__DIR__ . '/catalog/controller/account/register.php', file_get_contents(__DIR__ . '/catalog/controller/account/register.php'));
ensure_file_written(__DIR__ . '/catalog/controller/startup/session.php', file_get_contents(__DIR__ . '/catalog/controller/startup/session.php'));
ensure_file_written(__DIR__ . '/catalog/controller/startup/customer.php', file_get_contents(__DIR__ . '/catalog/controller/startup/customer.php'));
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/controller/event/so_soconfig.php', file_get_contents(__DIR__ . '/extension/so_theme/catalog/controller/event/so_soconfig.php'));
echo "✔ Deployed updated login, register, session, customer, and so_soconfig with Varnish cache control.<br/>";

// 9.16 Clean SEO URLs: register, login, cart, checkout, and remove en-gb prefix
$clean_seo_routes = [
    'account/register'    => 'register',
    'account/login'       => 'login',
    'account/logout'      => 'logout',
    'account/forgotten'   => 'forgot-password',
    'account/account'     => 'account',
    'account/wishlist'    => 'wishlist',
    'account/order'       => 'order-history',
    'checkout/cart'       => 'cart',
    'checkout/checkout'   => 'checkout',
    'checkout/success'    => 'order-success',
    'information/contact' => 'contact-us',
    'information/sitemap' => 'sitemap'
];
foreach ($clean_seo_routes as $route_val => $kw_val) {
    $chk_route = mysqli_query($link, "SELECT * FROM `{$prefix}seo_url` WHERE `key` = 'route' AND `value` = '{$route_val}'");
    if (mysqli_num_rows($chk_route) == 0) {
        mysqli_query($link, "INSERT INTO `{$prefix}seo_url` (store_id, language_id, `key`, `value`, `keyword`, sort_order) VALUES (0, 1, 'route', '{$route_val}', '{$kw_val}', 0)");
        echo "✔ Added SEO URL keyword '<b>{$kw_val}</b>' for '<b>{$route_val}</b>'.<br/>";
    }
}
ensure_file_written(__DIR__ . '/catalog/controller/startup/seo_url.php', file_get_contents(__DIR__ . '/catalog/controller/startup/seo_url.php'));
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/controller/event/so_sociallogin.php', file_get_contents(__DIR__ . '/extension/so_theme/catalog/controller/event/so_sociallogin.php'));
ensure_file_written(__DIR__ . '/catalog/controller/account/register.php', file_get_contents(__DIR__ . '/catalog/controller/account/register.php'));
echo "✔ Deployed updated seo_url.php, so_sociallogin.php, and register.php (en-gb removed, telephone validation fixed).<br/>";

// 9.17 Fix config_telephone_required in oc_setting
mysqli_query($link, "UPDATE `{$prefix}setting` SET `value` = '0' WHERE `key` = 'config_telephone_required'");
echo "✔ Updated config_telephone_required to 0 in setting table.<br/>";

// 9.18 Deploy updated mail library and startup setting controllers
ensure_file_written(__DIR__ . '/system/library/mail.php', file_get_contents(__DIR__ . '/system/library/mail.php'));
ensure_file_written(__DIR__ . '/catalog/controller/startup/setting.php', file_get_contents(__DIR__ . '/catalog/controller/startup/setting.php'));
ensure_file_written(__DIR__ . '/msbadmin/controller/startup/setting.php', file_get_contents(__DIR__ . '/msbadmin/controller/startup/setting.php'));
ensure_file_written(__DIR__ . '/catalog/controller/account/forgotten.php', file_get_contents(__DIR__ . '/catalog/controller/account/forgotten.php'));
ensure_file_written(__DIR__ . '/catalog/language/en-gb/account/forgotten.php', file_get_contents(__DIR__ . '/catalog/language/en-gb/account/forgotten.php'));
echo "✔ Deployed updated mail.php, setting startup controllers, and forgotten password files.<br/>";

// 9.19 Configure Mail Engine and Alerts in Database
$chk_m_engine = mysqli_query($link, "SELECT * FROM `{$prefix}setting` WHERE `store_id` = 0 AND `key` = 'config_mail_engine'");
if (mysqli_num_rows($chk_m_engine) == 0) {
    mysqli_query($link, "INSERT INTO `{$prefix}setting` (store_id, code, `key`, value, serialized) VALUES (0, 'config', 'config_mail_engine', 'mail', 0)");
    echo "✔ Inserted config_mail_engine = 'mail' in setting table.<br/>";
} else {
    $row_me = mysqli_fetch_assoc($chk_m_engine);
    if (empty($row_me['value'])) {
        mysqli_query($link, "UPDATE `{$prefix}setting` SET `value` = 'mail' WHERE `store_id` = 0 AND `key` = 'config_mail_engine'");
        echo "✔ Updated config_mail_engine to 'mail' in setting table.<br/>";
    } else {
        echo "✔ config_mail_engine is already set to '<b>" . htmlspecialchars($row_me['value']) . "</b>'.<br/>";
    }
}

// Ensure mail alerts include both order and account
$chk_m_alert = mysqli_query($link, "SELECT * FROM `{$prefix}setting` WHERE `store_id` = 0 AND `key` = 'config_mail_alert'");
if ($row_ma = mysqli_fetch_assoc($chk_m_alert)) {
    $alerts = json_decode($row_ma['value'], true) ?: [];
    if (!in_array('account', $alerts) || !in_array('order', $alerts)) {
        if (!in_array('order', $alerts)) $alerts[] = 'order';
        if (!in_array('account', $alerts)) $alerts[] = 'account';
        $new_alerts = mysqli_real_escape_string($link, json_encode(array_values(array_unique($alerts))));
        mysqli_query($link, "UPDATE `{$prefix}setting` SET `value` = '{$new_alerts}' WHERE `store_id` = 0 AND `key` = 'config_mail_alert'");
        echo "✔ Updated config_mail_alert to: {$new_alerts}<br/>";
    } else {
        echo "✔ config_mail_alert already includes order and account alerts.<br/>";
    }
}

// Check admin email and test mail sending
$admin_email_res = mysqli_query($link, "SELECT `value` FROM `{$prefix}setting` WHERE `store_id` = 0 AND `key` = 'config_email'");
$admin_email_row = mysqli_fetch_assoc($admin_email_res);
$admin_email = !empty($admin_email_row['value']) ? $admin_email_row['value'] : 'sales.magicalsb@gmail.com';
echo "Store Email Address: <b>{$admin_email}</b><br/>";

$test_mail_result = @mail(
    $admin_email,
    'Magical Singing Bowls - Server Email Test',
    "Hello,\r\n\r\nThis is an automated test email from Magical Singing Bowls server.\r\nIf you receive this, the server email system is working properly.\r\n\r\nSent at: " . date('Y-m-d H:i:s'),
    "From: {$admin_email}\r\nReply-To: {$admin_email}\r\nX-Mailer: PHP/" . PHP_VERSION
);
if ($test_mail_result) {
    echo "✔ PHP mail() successfully accepted the test email for delivery to <b>{$admin_email}</b>.<br/>";
} else {
    echo "<span style='color:red;'>✘ PHP mail() failed to send test email to {$admin_email}.</span><br/>";
}

// 9.20 Deploy null-safe image models and hardened theme image handling
ensure_file_written(__DIR__ . '/catalog/model/tool/image.php', file_get_contents(__DIR__ . '/catalog/model/tool/image.php'));
ensure_file_written(__DIR__ . '/msbadmin/model/tool/image.php', file_get_contents(__DIR__ . '/msbadmin/model/tool/image.php'));
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/controller/module/so_listing_tabs.php', file_get_contents(__DIR__ . '/extension/so_theme/catalog/controller/module/so_listing_tabs.php'));
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/controller/module/so_extra_slider.php', file_get_contents(__DIR__ . '/extension/so_theme/catalog/controller/module/so_extra_slider.php'));
ensure_file_written(__DIR__ . '/extension/so_theme/catalog/controller/module/so_filter_shop_by.php', file_get_contents(__DIR__ . '/extension/so_theme/catalog/controller/module/so_filter_shop_by.php'));
echo "✔ Deployed null-safe image models and hardened so_listing_tabs, so_extra_slider, so_filter_shop_by.<br/>";

// 9.21 Deploy Product Specifications, Weight / Dimensions display, and Admin specifications fields
if (function_exists("exec")) {
    $git_out = [];
    $git_ret = 0;
    @exec("GIT_SSH_COMMAND='ssh -o StrictHostKeyChecking=no' git pull origin main 2>&1", $git_out, $git_ret);
    echo "Git pull result (code {$git_ret}): " . implode("<br/>", $git_out) . "<br/>";
}

ensure_file_written(__DIR__ . "/catalog/controller/product/product.php", gzuncompress(base64_decode("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")));
ensure_file_written(__DIR__ . "/extension/so_theme/catalog/view/template/product/product.twig", gzuncompress(base64_decode("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")));
ensure_file_written(__DIR__ . "/system/engine/controller.php", gzuncompress(base64_decode("eNqVUsGO2jAQPSdfMZWQCAgSRNUL29JKFPXQlVq1VyRkzGzirrGjsQOLKv69tklYNiyim0tiz3tv3rzJx89lUcZZvx9DH76UjD+yHCE8P0pUM0bWVUKRVbbQdKzBV6YESviORKHIdbknkRfWF2enQ8J7MB6NPsDQvcbjwUl0APd2nUJSWFuaSZbtdrtUuxp3tZTrTdYLslJwVKY21GA9zuiKOKaa8qzGmOzbz/vh+3TU+DVY886o7TYOmMWKbdC40TG486XF772xuFnMVS4U3jX5zCQzxk2nLGkpkZpOG/EkFCxeZy9+YS6MpX3oxVsS8DeOvHrkdbaMbqtEXiYqSVvkFtc3CdCh+usufu7lHLiriltN/nzsXzJimzcINl6qldsBPFSKW6EVLJe8UU/+X6zno4g6thBmOG0u4dO5/ehwNsFymaNte3c4oXLoPOL+uURoK1KgV39cYlc9O7XkjN6b1IRgSzxA0rI2nBbMJAF6tB7VfS5wXjng3ATRAVC6HzoQbEF6Bwp3sJg/cSy9k6Q7J9I0cRuq5BqUtsCZlHBKxAlBF9Jg0r2677pH3XY65mY6zXU9Z2fLZIUXsW21WF8NzbwMbfBSy2Xo2a8tdjg1dSqDBnyxX2HM2za80lpetRrUWhv2hODu2upOKw7mDvE/0tmcAw==")));
ensure_file_written(__DIR__ . "/msbadmin/controller/catalog/product.php", gzuncompress(base64_decode("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")));
ensure_file_written(__DIR__ . "/msbadmin/model/catalog/product.php", gzuncompress(base64_decode("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")));
ensure_file_written(__DIR__ . "/msbadmin/view/template/catalog/product_form.twig", gzuncompress(base64_decode("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")));
ensure_file_written(__DIR__ . "/probe_cache.php", gzuncompress(base64_decode("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")));
echo "✔ Deployed product specifications controller, theme template, and admin product form fields.<br/>";

// Ensure specifications for TH-VIP-SET (VIP Nepali Khana Set) if currently empty
$check_vip = mysqli_query($link, "SELECT pd.product_id, pd.diameter FROM {$prefix}product_description pd JOIN {$prefix}product p ON p.product_id = pd.product_id WHERE p.model = 'TH-VIP-SET' OR p.product_id = 3588");
if ($check_vip) {
    while ($vrow = mysqli_fetch_assoc($check_vip)) {
        if (empty(trim($vrow['diameter'] ?? ''))) {
            $vip_specs = "<b>Product Net Weight:</b> 6000 gm, 6.0 kg (approx)<br/>\r\n<b>Material:</b> Pure Traditional Brass / Bronze (Yellow/Shiny Quality)<br/>\r\n<b>Quality:</b> Authentic Handcrafted VIP Quality<br/>\r\n<b>Included Items:</b> Full Traditional Nepali Khana / Thali Set (Thali Plate, Daal Stand Bowl, Meat Stand Bowl, Curry Bowl, Salad Bowl, Pickle Bowl, Spoon, Fork, Knife, Glass, and Karuwa)";
            $vip_specs_esc = mysqli_real_escape_string($link, $vip_specs);
            mysqli_query($link, "UPDATE {$prefix}product_description SET diameter = '{$vip_specs_esc}' WHERE product_id = " . (int)$vrow['product_id']);
            echo "✔ Populated specifications for VIP Nepali Khana Set (TH-VIP-SET).<br/>";
        }
    }
}

// Ensure specifications for TH-SPC-SET (Special Nepali Khana Set) if currently empty
$check_spc = mysqli_query($link, "SELECT pd.product_id, pd.diameter FROM {$prefix}product_description pd JOIN {$prefix}product p ON p.product_id = pd.product_id WHERE p.model = 'TH-SPC-SET' OR p.product_id = 3589");
if ($check_spc) {
    while ($srow = mysqli_fetch_assoc($check_spc)) {
        if (empty(trim($srow['diameter'] ?? ''))) {
            $spc_specs = "<b>Product Net Weight:</b> 2500 gm, 2.5 kg (approx)<br/>\r\n<b>Material:</b> Pure Traditional Brass / Bronze (Yellow/Shiny Quality)<br/>\r\n<b>Quality:</b> Special Authentic Handcrafted Quality<br/>\r\n<b>Included Items:</b> 9-Piece Set (Thali Plate, Daal Stand Bowl, Curry Bowl, Salad Bowl, Pickle Bowl, Spoon, Fork, Knife, and Glass)";
            $spc_specs_esc = mysqli_real_escape_string($link, $spc_specs);
            mysqli_query($link, "UPDATE {$prefix}product_description SET diameter = '{$spc_specs_esc}' WHERE product_id = " . (int)$srow['product_id']);
            mysqli_query($link, "UPDATE {$prefix}product SET weight = 2500, weight_class_id = 2 WHERE product_id = " . (int)$srow['product_id'] . " AND (weight = 0 OR weight IS NULL)");
            echo "✔ Populated specifications for Special Nepali Khana Set (TH-SPC-SET).<br/>";
        }
    }
}

// Ensure specifications for TH-NOR-SET (Normal Nepali Khana Set) if currently empty
$check_nor = mysqli_query($link, "SELECT pd.product_id, pd.diameter FROM {$prefix}product_description pd JOIN {$prefix}product p ON p.product_id = pd.product_id WHERE p.model = 'TH-NOR-SET' OR p.product_id = 3590");
if ($check_nor) {
    while ($nrow = mysqli_fetch_assoc($check_nor)) {
        if (empty(trim($nrow['diameter'] ?? ''))) {
            $nor_specs = "<b>Product Net Weight:</b> 1800 gm, 1.8 kg (approx)<br/>\r\n<b>Material:</b> Pure Traditional Brass / Bronze (Yellow/Shiny Quality)<br/>\r\n<b>Quality:</b> Normal Authentic Handcrafted Quality<br/>\r\n<b>Included Items:</b> 5-Piece Set (Thali Plate, Daal Stand Bowl, Curry Bowl, Spoon, and Glass)";
            $nor_specs_esc = mysqli_real_escape_string($link, $nor_specs);
            mysqli_query($link, "UPDATE {$prefix}product_description SET diameter = '{$nor_specs_esc}' WHERE product_id = " . (int)$nrow['product_id']);
            mysqli_query($link, "UPDATE {$prefix}product SET weight = 1800, weight_class_id = 2 WHERE product_id = " . (int)$nrow['product_id'] . " AND (weight = 0 OR weight IS NULL)");
            echo "✔ Populated specifications for Normal Nepali Khana Set (TH-NOR-SET).<br/>";
        }
    }
}

// Ensure specifications for MS-010 (Product ID 4683 - Complete Chakra Set Small Size)
$check_ms010 = mysqli_query($link, "SELECT pd.product_id, pd.diameter FROM {$prefix}product_description pd JOIN {$prefix}product p ON p.product_id = pd.product_id WHERE p.model = 'MS-010' OR p.product_id = 4683");
if ($check_ms010) {
    while ($mrow = mysqli_fetch_assoc($check_ms010)) {
        $ms010_specs = "Name: Complete Musical Set Tibetan/Himalayan Singing Bowl<br>\r\nSize: Medium Set<br>\r\nWeight: 4100 gm (app)<br>\r\nDiameter: 18 cm (app)<br>\r\nHeight: 9.5 cm (app)<br>\r\nType: Handmade, Jambati<br>\r\nColor: Maja<br>\r\nDesign: Plain<br><br>\r\n<div align=\"justify\"><b>Every instrument is individually tested in our acoustics Sound laboratory and accompanied by a serial-matched Lab Report Certificate that details its exact fundamental frequency (f0), harmonic overtones (f1), musical note and cent deviation, visual acoustic spectrum, precise striking point, and verified Therapeutic recommendations</b></div>";
        $ms010_specs_esc = mysqli_real_escape_string($link, $ms010_specs);
        mysqli_query($link, "UPDATE {$prefix}product_description SET diameter = '{$ms010_specs_esc}' WHERE product_id = " . (int)$mrow['product_id']);
        echo "✔ Populated specifications for Complete Chakra Set Small Size (MS-010 / 4683).<br/>";
    }
}



// 9.22 Deploy SO Listing Tabs Product Feature selector (Admin & Catalog)
ensure_file_written(__DIR__ . "/extension/so_theme/admin/language/en-gb/module/so_listing_tabs.php", gzuncompress(base64_decode("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")));
ensure_file_written(__DIR__ . "/extension/so_theme/admin/controller/module/so_listing_tabs.php", gzuncompress(base64_decode("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")));
ensure_file_written(__DIR__ . "/extension/so_theme/admin/view/template/module/so_listing_tabs.twig", gzuncompress(base64_decode("eNrtXetvG0eS/ywD/h966SSUYlMP27uJZUnBYg8LHLDALW5zn7w6ojnTFCceznDnIVtn6H+/fk+/5kU2yWGWudtEnOmqrq6u+lX1c759AwsEQ5SB5+dv30CQxuUymcZoXuAHL1/chNEjiMLbUZAmBUqK0d3LFyf0YRDDPL8dreADmjAO9J32khDBKEHZZB6XUcgKaCXmcQqLCUrEu5ObWVkUaQKKpxW6HeXlbBkVIzBPsyUujP89mSNYlBkKRyCEBZwU6cNDjEsWaRoX0WoEiqggv3Fb0tlvMUwezh9QcTrGsmdPU8Z8msNHND7DDRwJOWZFAvD/JqssWsLsaXR3E0kRIZjDCSHBTy+iO9CN9c0FeyLaBc2q8jIIUJ6PQJoEcRR8vh19dzp+BYMiSpPx2fkjjE/HhNsUhVExPvtI3moawIWYfk7PPm6qjSlMQl4RVYvV/hVKgiie5P8qYYYmaVdV6HxvLmCtOqJknrbrIkFfdqIKWo9bE7M0/cybD/ox05q/yNCcShPAJEAxrWt9wRkTt02HmIR4p9mQDK3ip04tUbjLNtxcYDemfwL8z83i6u4bg5IoeZhSkad5SgnwK1ksjaV0GS4bZOVyNpKvyT/fvifeDqrXIEqUXzn4/lktfhNHNsdJVKAlbpmi5erlOXlG9HSnPy7Q14I38OYijkyhMEYRuaraby7SmCGe0ET1RxsGYn7RHKAsS7PzLzBLsM5AlIMQzXHJkFeicoExygpA/13bn+gr/rmExGcmQZQFsQJYelXPz6wL/zCZ6HjLfkgLCuI0R9wsZ/kkjPJlJKUZ3f1QREuUf5RIN5no+jhhasMtZQ2qWs6xT20zdhT5uLn9AjgtBQQLFHy2mi6Y7qrRdWJTgLNkJk8tkYkpTmP4lJbFfvoK4P9n7qVaMsxC4avmcy0FOLlZvKvygwTFE4oHo5qgonbVjCMIA453ojYFa6yaZ2n4ROoF+j83JEAAFkIoArA/KUAuUbFIcVKzSnOcXGAhmEaXJQFcmBUXNLgQVY5o8mMkHqIN5OkizaL/Iy4e41DBwF2zgCz9kovcBr8pJf4l8BHg/00KOMM5QJZSyIezOMqLqvyJAm+kMME1wF0IxSgocGgNwe0tuMTdRhr4iJR+VPhoESeOks9qoCCc6bMenMFNjrvW6M64JF55Qd4oVZ8QnGWBBIbhdJmGZYyEXTPhZGCkPzj4MtrvsTwFiMAtuPr+WX1M0PgzenoDOEMcJthfRKNYRgCUCmw1Cp2vMpTjYEdhc8Q1wPics/9MmR4UrRDmwsGpZkaKaoDa8irNMXTcXoOtc6sDf5Dkt/ihyZJ0MMwiDIvYPHFbSYSECQ5ErMQEk2D1YTZgBDShT1xdy9I/GBeyg0FVZwKXSO1PvUPrezQCr8GV3lVmpGX0ZSx9i0LBCf/ldjjF37RAjNPX5eTq7ciShesMkxIzA7admSaWq2VOLLf5XtOFOloq43iSRQ8L1ccN75Q49UOIORbo9ko6aoaW6SMCRmKHU1TaIXZ+R0p3ydRZRTy/Y7y0DlTChNpLMrArDaV4iT1wwhQlbYQqV8EcV29YHQlwKvfO0FSUrHBUZIC9iMIQYa8l9sd8kfgwkUD8jUcOJX41utCZxHCGYkOUd2TwO6GgTl/TUeftiNY3ITUQ0JuBvHgisDHHTjX5gkhXXs/SOPxIqNPs+tX88nJ09yOOswIi10jqSW24U/KAJfR39aVoASA7jQquN9X2gA+6Rl1F/mgU0dVO0hOhdKoYoWYdEWh3r2IYoAVWEMqaWstHLqTrFIVrkZbD2AhcGOIbtnmip9dUEiu3djaetIs7FVDsU6bPvFHuCm1/cIhmP2g3+N62SlKoKTPYHdmrrLHdaGXRtSwXaOje2XqVkHNpGoCAeCJoCR8oyIu/c6uwK3oZJWpdhQMhUVEWrQg+fSKxnNd1Lv4g2cDz8/0nqan7Tl6kK1Z1JfKG+tOkvjrbhTU5XVT3n8ZVnfeqi/2yJotrMB6rMUJ1exLAorsrzHsMcGvSBOE/MAGYR1nO4ILRGuggwCAiGVZbb4l44oQdXaHMwdbpmCEpmhmmAhi27ly45k7T6tDY4aHvrPjCsidQr3rRGpeMPfz35CalalK6oc4l7tR3EvsZuVtRDn1ghbCm9Qpaskc8Ra6K3/DDV1DmRbqclhmZGFg3IlVM2kNSVfYYk1piUqWqbkHJUK0KotUr72FJqXVtuNR4bBCYCINdxSXFc9bqnGHpmhmoigbDD05cp7/XGLWfgBBG+YqvKDGbGm0wVCHMYvik8WsPES6qnQ29ufUxf7CVoQCA46XLZN1IxEfuFo92+7waycmv25H4y1YlXdV4QjmfZ3Ibqcn7soZPkjayIbYe551k9yVndx10kN2Ja8N207yABZ3/X9c1GYN2Z2Tl9uN+vJGKz4knvR2NEfr1LpTAWUy2Q3jwMAwE7cw29rP1JO6ukc6t2K7Hmb+NWXMxjIOzCVkw1efE69YMiQ3mqYyJuvdGhsh86dhcjeLrV5QXXxfCzKtlZOnB2kOYYQXfjl6JIKMvSMu1TGv9iVQraOirqhuLrER1Ds/jLTizdlW51pd6NF/osMwCNGWm0af5GmE/LRikPZShUW5NJ8TC1tCIQtZPHxphD20odFvTBVktXkcZKl0/beiUPdShEm5PH0sy/lhDHwpdT31olH30oRBuTR9oPseirKEQjbCfRgzSHirRKLemExg+kj2D4RpaMUj76cUi7qEZg7a7btT9Ca6Qru6dPmmK+2AOQwTyBc7I7cCsNJ1uquJto4kvTnBmT1oE1+qqTXqr3B98ewXIHjPwjyLNEPgb23w2mdwB8MpMitYYGmCefEPbJoN3lU81TgD1A4WqOCvZNF7oNGLoNmawRw1K+7Wxg/q8ZgRhshaTScxKp1H4RvxJ02Myt6TydY0w5JSxcAxwC04lP0CmJKtRSsXqjExyCpIxcDK2p64qvmRWj+zyE5XiDrmrCjDpG0YBDRNYpo3Xpu52QcecoHOekO076uNSm3oNLTrNy/k8+rqJ16h8OqyGKKW1MTbYvtPUrVJomrA3m6ivzVUGjbLLdpKeFrEDO0hXKJnS0LqBEUgm7RYgi7ZOsWwRMkkmy+RliYHaqZVCOs26dEFMs7quqFmBG8ZMbo4mLx016frG5sjpDTjrcHJoXsBPpl29JfuVNthaN5syTpcdoJAW3KcXVNJqoKaowpsHyKo2Mf1K3qPRezT6Dx/eTq+uPnzYYHJd9szVQdn9lcPupTYGavtXR9v3aPs//eln3ONXPkz/7UGZ/luH6QtlDNTy3x4t36Plv//5Enf4T16ynXe9TH/Plv/OYflCGQO1/HdHy/do+ZfT9z95SXbeH5LZv3eYPdPEQI3+/dHofRg9me+akkl/25zWdQDJst3+ZVHVBUzl7NQhKn0o/qA8XNcZCAviBJLVJk4gmWzNB6i4w7b9SpXTOIXhkqwcVdOylYqw2saiwJgojG2UHsvNvhv7EMYk/aS9XKTczYFNWn2HI5u0nDa82KurMXm0c5dckT5DTraZqzEO/2axRn/StnDs2Mg02l3ootX6Dl6sLR3DFy28zwCmaS6DYZROoiSOEuT2mWoXqCK/257VNTHKeKSFSaZ7Za8ovQSHbHXgfzgWuNiVHA5lziMUh9NVhkUL2Iq50wncGz7XkNW7ZM5NnHX9P5xuu+yjigAW6CHNnrbdP5eb2VK7mP06y3fWviHyiRMh/FYgg9/JxudTON/uR1M4gQqCTgzsAIGtCPizWabBk8ASWeputEVTswpeWAdVlHI0ybwiSYFltertPBeWJI3HP8DpP0S28HcGPjn4ryR+Oqsx2t2r5W0Htbz1r5a/49TOVs1fyZnHjXXjQy2XQi1JWtSpRl7s41TapS+l8eM+4PQvHBKlvrppikHai+ZHjicu2FMyfR5MJdIQoNsYFg2m3mDRFLYVFg2CncDiH21YZHZLG5wGco8rueRNqGoiLdfe92S2OkcwCxbVrUb6Kd9fyYQBJ6EMuIHjHixSwGjBD3z4c35+3mAMNVulYFmkQbpc0WupRul8PrJ9gBxXoUdinW3kbMMsXYXpl2SyRElJbqgqbXiYZdYjcRmxuIqvVuwvKI4B+RfulpGYAVhGyWTBJgDAz5errx/BEn6VT96+pY/SR5TN4/TLNW3sRzBLM6zca3C1+gryNMajyFdhGH4EKxiSewoxJ0LFSk0IMpX5NXhPn8Hg80OWlkl4DV7NP5D/+2gjGx9AGw2pbho7N96QgbSd2LUpBvuI8UTy5eNiriPZrHe4vZcf7SsbsRLLnF9YCZRrJySHoMzyNLtepVFSoOxjdf1YnQD81LLVqKarvgxen+7VXYEtLbVR24Wwxnj/RTuBT5jeFIZF8r27K8tEjR0WXNSBwc6WXBqAWCrLtfNUvnNjbocmaqs6kl13fH3ZBLAWQxeyvnRA68tWbLVY66BaOxco6BQME4/cs4D1VdPbsdmfkoe4+KAJmCrQkeQcZV72ghlBreNLnUjOwbFr2No6l+ggcT5Urwyq1F57Y1C/O4MkQ+eVQY2HZve4gLyI4nCqwt/agKZx6gBrWvndgtva01i60D0njQxde5qDjJIgLkPkc3arXlCPYu1s6nEbfdZrApLcM7+bHtpwCrJV0L3OQDrSAowyq2LBR+IvPeWAjOleMkHSmM75ICu/j6ywPu3jurOOHH0aG1Lf1yR6gsH6J4+cQZ6x9RPfOa9DivJiREdH3ZsEeY1R90klWnyPW8aEHHigXbhmcZhevC3hq9VtspCv8jluHfNyNJB0dJQ8bHQykPPocDCQlxyA5cuGq8cC5TPfhi84+zB+wevoAD4cgG8TiSPymbidpThqrR3uK1NKDyO90bRmn6dWXxt5jU7pKavR6tsopzEk30pG47JRIsRNKafAmaW9Rx/Q+6s6q8BKw/kEItsiuVGUbouoHzzIDI7z8TJmiMI6diebDAkqtj1GBLp+9r9dUlePnu6rLzrHH2VDEZxNYRyL7Rw4cFzRT6JVM6hVBfjl6MdRY7hw3I8n/PzH2vtvcPXqDqvmyIEH+U3Su+40tsOZJ1lqxvF6hO88H10Xz7X53iqw612zWVS3ppS5m1cV1BetywTargUexEZybX/f1LrAaB0sa2W5q83hliDtCGiR7BMCG5bOqm/i2UmGrf9ea2huFaig66ig66qaa1nNtSimLrVZ3//rtNRmrW3VS+3ctCC2J1z90bU9oX5UY1WjwJ/1ruu6nEWIu856Zqy3d1yos9nUrtg1LNhZXPSVuzZZ/a7g9btzSsuIHb3naVXP5ry7ib9torznjFVj2x2vh5Wu6rpxIGffdNWZTHVEk9qsSnHBKqnSJNw0qTKcnEKaKnRtyQNJqdY5K6ReuLuTk0L6CMHjaSGdcYcTQzrBYZ4aMsZbPU97GH2x8cq9tjfc15GUeiH9iLS7s0Jb6azLHnrgO9C33DOXG5lPk4z7PSnkA/qW8Os0WMAM4liS7e2Ysi1JN7jUafY/FrUHmw4d2/PadiFz1T4tE3/T2o7qfGXxzpYczvo9ET8KyIent5ENqJy72bdKcbD5gNqINRBd64/BZgR1Uh5gSrCN/tpvUlAn0u8wK9jGVEYAxUak2ZPXtTfJtdPSmyw9pGW3SjH6qpvy3NemD5XrJhs+VD7HzR4+0oZo+RDMH4hev0Qh2dK6nyzaEKPdqQyCIebPpmrt5NkosdXM2azLV9pst+FwcuZKdrYktH/rZ3L0MX9GMWz759ptcgBWZEcewCvz7wKyFVvwgfoZ6S5z09rnz7Z/WNY8ZU+rP95c0PnmAva5ui73F/CCW7++QFS0lUsMJjrzLV1lYDTheKGBSz3th/2p/a4IpwO4AmHX1xDsIGvRPpzu77vt/T7YfiCHcTWZe06/6Hoe5PRdrYgHNnfnv5v2N2tXK89xIc+1bEGxB8fYGCUPuzxSa1TcYVFDJxjGqRNTfY4FOr2EMcKy6H2t0xm1eluks1pzOLMN6vJZFvGR2ObRW2HXPYYrRAcWyRXJ1wRmVf+Djuo1gh5obN9Ox+0/ztdIdYz2LghUsGoPMd9ZfQfQdJENI/67FWpnAc5yRi5Qw8tTRuCWwFdeUNe+w8sOVlkUeBnVU0bdMwJa/MByASrzmrDN9Dzo+G+JeKCR31837T/aW/Ic43wDmMEwnBbpNIBZ4QPSFHbdgU0hOjB4UyRf01pV/Q8a6moEPVDA207H7R/8aqQ6QmADBH6J8gVZ8vSBf4JXd/ATFAeGfELsNe1U6nzQmOeS8kABz3d/7R/qXCIdca4B58j2D5h5GblyVt1RjhMcGMhxqdc0UKHvQUOcQ8gDRTi/nbV/fHNIdIS3BnjLYLHhnaA6p+7gxsofGLYxode0Ta7rQSObLeOBApvPnto/rNkCHVGtAdUeI/QFq8QDqjFO3VGNlT8wVGNCr2maXNeDRjVbxgNFNZ89tX9UswU6oloDquXQz75owqc7opHSB4ZnROQ1TZLqeNBYZkp4oEjmq4/2j2KmOEcMa8CwBH3xAWGYTXcEw4UPDMCwxGsaI9HvoOHLEPBA0ctPB+0fuwxpfo/Qpe4EhAVOfOCTp6+mCXY73OgpGtCOfbzkQLZzCk05dnDyV+amTUnha5+mqMfb1sxK8MP7Glq0hA9ompRLH19Ek8y6H1iXJLQ02O8HoiplOI6JKy99fypKsvbxrSjJ7N/s/qB1LjRmutrhjcZaJ/lzuX7udiA5sCZzz7RG1/Mg8+BaEQ8sE/bfTfvLhmvlOQ7lG8AMt1XgEAYyH6imc+wObzrdgeGcLvyalmv0xaCRr17WA4XAbfbg/kGxXrAjOnZCR29Jn8FyHXw8xETQkH5jMz6A5LBB2IPHyG104pBQ8phEdoHJvd7K2/Eu3sHewFt3767rtl1e1tMsqt87dg/vZl1xn+6uJvy73ptr35a7P+usvRXXeReuKO3JQD1fgbvNi2+3nIAqF6ZOV3CX15HUSdA9YTUph2HYtZq1TP3TuLYl94b91zP15BF1FXjzkdoKDvGakhlMEiz/xmM2F7/uO1ZUqgPbuqKKvuamBK0PBr2ZpU7SA93Vsq2+2/8+lzqxfo+fG2JtnXAI2xQY8zgKkbiTeW047AeDFvztboMABIsMzcmF8/TLt4sSq5RbjdZs/owzjJYPE1o2gVFMbgFfPoA8C7T7SslrGvthXBD+XF/i3nHjK/cqFVbYHJZxwT87fnMBm3MW/TvnTtOvatB8XkkJfnEWoNsJ9ASGM7b9Rk1C6mpx+EiPzEPjWpNtNPjjoP1urxMkXLEd50nU0kOcLlHlq/cA1+SJTukpIdfq85WEG43YVeLt2eh5K3Y9y6JV29nehzTnoiuu1sadMzAGrV8r9zwfY7bjQO0cJ+WfR5sbLEunyizul1IRCi2tAns3XClXS4ZCirRmKbSQI1OhWt/Avre2HxHN5ygoajYkuoyL+MRNKb8hw5DwPfqA3l/VGQBu75Sof5ov0i/TOIXhdJnKu2VKN3ZtZ66FNdffZ0u5+lp9gJUbzKdKuRoUAxVPfG00Zvw22V3MOBw/SeplirEktzhYDr6zpF7U32EakpccYjIvteg4SsJfmUdJJIWvoySiHm9HSSrBD2jGHJFPd+/LllHc6RQUKTZIK0ax+zQUeW7dXx/7PAdFa/B3P328vRNQW8l86Hxmto+0h3xTceX0mXW9QHBsdwRRcjDJj1SGYujVs84JULWGIYi7ZCZXI5mI3I7EX7bqtBWa+tzE5H5Zw4kvGDQnObVLHGYL/EnbXRedWlC79nEgWZo4GQ0f/TmqwrTHtQXwcTDuqmpFDU3q4zWcVqE/+u3Rb334bZymK/+OS7h291xSenCuSxXj8F32fAPnJQyO3nv03k28t0jLYBFm8MGf60qWHT6BKooOxmkrfagfNa0eruGukvroq0df3cRXl2mZI7++Klm2+6osOhhfrfSh+KrycA1fldRHXz366kaHASA2pEX6iDJ/zlrx7LChX5YdjLsqKlG34StP13DYivzosUeP9TFL/Gu0RGlZ7GuBxRCj+wwzJxjiooupWnv5xShhLMRY9J6WZMxafS3O2K05nNVFIfs/VojAwX6dgArR3QVo8SE7AFNqvfnT9zXGz2k9mz6r0bfhi3ZswezX2eYFw0eYBNiSdnrzHJpSE9joPCfj0eX8Jiu5202xpEaYISiPafIma6csxTNncle5gChI5ReMB5/lp3mxeS8LJh26WRTdbz/LVqsdXT1s62lR8qC6Gg8zpgEMFsjfeE6ybO94WXSf0W3tY45S+p6HCCudD/IwqlO8AzuB6q9r9nfW1CnL7/xGdYYbBR5geLpTvWLoD+AUIVsRrio7xARe0Y6dvVcvjdRdpfKUtyt1+UraNfG3nrE7Hpi/+Q55pt0psf5b9cdrcKUK4tqhrnEUPwD/hzwh3XB3cgIAKQOALC/+BCekfMXnJg+yaKVaxsVv8BGyp6O7mz9MJoTTd6dj2vOfqNX8cxzAAj2k2dM/x/fjMzo6Ip9JjVGBTr9R6fK0zAJ0DeZlEpChyWmG/lWivHgDMpSv0iRHZ4CVPPnuHP4Gv3K6k5Myi68Bri1EX89Xi9UvWVoW6BYLhpIcM7rI02mxQEt0wTRHfpPPT5MvheLBUa4JMxVy/oChNJsW6WeU3GL7qH5hy/hhHsUF/k2bNsa9gNUepCH6n//+z79gPmmC/ViIf/aGS0lA41esMyzqb3majMXzvAwClOdKw8lr2daTE9H80+/Ol3BF376pCkcFWiqFcemizBLlwQkFimtACn4aE5HH92+qt9SFxVvR+GkUju9lGWlLz2dnH/nf/Nkzf/DMOLLpzesa6ZpNAgtyOh6LCiTFqzmCuEUonIjiROFMXCr7+P7sPEPL9BGddiHGtrdaYS85HVNgIAjVXgP+PSZH6iV0QTCHk2WUlPkkiLKA3ktwEd2BipJqnVE2HIwXNX66l2DqrpyeuSce6NAQZhKFZNxLTlS1a4eQCFBWCRXN6NPj49caw9djBuzN1TKVufUh5s5FW6gxcVNiMgoDAjAM/8Z89Vc4+zu75eevrL+Ecb0w+1pcBsQfNGmklbbJXNrqYR3HJwlXuCW4Fdfg3eoruPzYbE1ACVSSQ1BmeZpdr9IoweDzcU2LM4TuZXgv2tQ1JfT5JgrnHHqovaZOJjy714KQD6l3NH3iXOL0u9ORgoxjsatNGNb9tcjcGUyekdNx48sxN/6TNvJPrIfHV+N7zAHrb3U65hzHb0CRlYh1zDNxwxeO4G2qPEcwCxauUN45kmuBfAtxnIvcK4w3RfGaIN4Sw/uEcDOCNwZwd/yWHxHA4VtGbyV4v1CePPM+f9MUuJ1xu8kaRBSnhK3gXVkdc4a2mqirH83uaHaGMWxsdU0JQIiFfMB52un43AgEGDzHQRwFn8eKWrn4jzADK5jhzvwPHMBuwXenxSLKMfrSZzwS0kLksHNV9BwWRYZbTvKxDNErmU7HtdJhAXizKwZ9Yy22QCyDHqM7aEZG6r3oRwT29Vvfud3K0KF/U18yMfS2GRk5aSXJpPF/bD2o9pCXsxz/uPqJE3LmI1c6z8+1i9A/es1qeD2iOQCCweLUklMKqsvHM3P6H9fQfh6hOJT3pmJMHPgY/99yaK9i9NZH9jUW0TrEt+jWGuvbXJqGE+11bm30b1W90TSAxq3PNIAgXG8aoLnaTaYBWjt1GHD89k+csEJiTaNbQuKLi8kEGwObd+0xMXtxQfSK8ZEhJYDX8yjLC9werNTTMTmgzjskmpsD02AR4XbJgHh/TRef6kanhIfQuDLlxGYYQ7QqFnTBBteM3UK08ZmsenWnJOIKShGdGkXGQgYLMnRXlP6N93usdDhtCdM61gN9d3tJm3TyzTGP1tyoqlVdiZV2KX7h7pVqRXytDtFWzzp3hknV2hGKlNvpg4Z2NOu/oSmtumeXOtC0ppf2z114pkrMJ3aZaVTYrMpG2OgXuKcZufjc0SNWPzrrN5i76jdEbKpftSOnRWi682oTrdrtot4e+nXaV6uKu+i4h5LV+CnCJz+0J24eGTcoOQPFAhaVpulVXlN+bIE8HbM4di1v4XpDKYSVMxkCnM6mMTqP04fTil52GDhVmWK/IE1md8BZjpnMyBpnzvPGK1Uh+iVyjP4cj/v/QhI0PDicJDhRH6t9862FmMVZN72pVudkq3ZftGdrVi9+NO2j3vwMqj6o5m5UH3irF9mBRfWSPiu2TLZJlKzE1oz4DZiydJ2WfSU+DwOxRWDFTMavLZvGxT5F4f9Wn5IRZUf3DTbJammwOs2Nz2mGRtnWN14fg42IIHQUOxIjL83mHLVIBU+DMi/S5c71zKolN2d2ULRS2L+m6eWkHRVdCdKqaXcUhLMpjGNxcUMTdlBHVVgT17uqz0k7rO3+ePfnOAZ8ktQekGnI2WWa6cemMU3NoKaaatC7ox6WEvRlnVQ3xKF2GsKnPomuSdOa5moybiPRbWhFU5rb0BBF8eagUuz6+fYNzNO0QBl4fv5/Cq8p7Q==")));
ensure_file_written(__DIR__ . "/extension/so_theme/catalog/controller/module/so_listing_tabs.php", gzuncompress(base64_decode("eNrtPWtzGzeSn5mq/IeJTpshvZQoyc4mJUV25WzvJVW5JBspd1slq6ZG5FCaeMjhzgz9OEf//brx7AYww6GkPGorrrJNAo1Go9FodDca4JfPVjerjz9apousXqXTLPp+lS2nadW8ep42aVFev3peLpuqLIqsevXyXZMt67xcvjorz2+yRfbqv8vZushOPv5oWqR1HZ2V3+Z1ky+vz9OrOsoQfFZHrwzOs/d1ky1evVxe58uMYI4+DD7+aLBaXxX5NJqvl9MGOony5Sx7N9ytswZRjqIPADPYXYguo9MorsukkN0lDfQXn4j65iav954WZTqDf9Pl9Tq9zoZxpkmfQKsGaZ9IRBMXyziOxwrJtFzO8+u9p9dZM4zll0SjTKaSP/FodOd+p2lRJPOySlZVPs3u3PMMvl/EN1k6E4PImyKLL4FBmiTVTmHjcAxFA9QCE951tDYgsqE3chhcVgzjWVbn18vJVbpcZlUc4JCCa8qymOQLwN8BpMY7gf+z67J63wN0VQGTp00HZH+BuCOOaVnAzNZv02Z6k9UwxaXAJHApkb6IcVVQbq+rAvrIl69x1hcLwIwQv+TLeRmPo1hPxmkc7Uc9REWKCO8ync2SBmWvveebbPq6XDeyb4C/e9eBjt/m9Q3yt6XzdDot18tmosEevH/g6yqtspbuleBMFNR9eofFEak/+Twa5jVQMlRtq+xf66xu9p7WWfUmqy7ir8/PfziLL0fRp59Gw01Q0Slov3IZj6Jffol6AR8CQUKBRoE/lkVXac0YExpiXRdCkF0st1FW1NnD9AETwvq4VZrGrpv5KlFfKC6xLnWF/l+gPpOfh7FcoImvff2VUjd6jRwoLZnN03XRQEFaVen7IRYO4vLqZ5z2GD4PTp+6enMsgXCDFRAIAnpelqIuTkRV5NRM13VTLgQj3KpU7I6xi0sNDDTvtMpXEgTqJaUjBTTL65XU/ImEjyWSQ40Fxtysa4PclIv9PanX83n+LnZ6zmFPT3D5gL6sgNcKZ3IFPHitgZZXqBHXi+WBbv7EqzpsrzrSVY+9qse66sirehK742jer2Dyb8q3Zoio0RclKATbuCLVoiVtW66raUbwGr4W6ftkngH7Ks3VA1WrlAqv1RMTgqg5iJoCtf2Z2QlXt9Te5MUsITB8dlU5SM+quXFrNXF1WTV64Kt9ZbNwkLKaZVUuFgNCfXX23AiW4BuIySJv3GmWEPM8AxI1JrH1towzR7AMJ05jeqS78ZDEXFZ1PWtthCO9SlApqNl0+YDVi/RdMr1JK1iEWVXHUu4+oxA56LA2DEC7ZFFypevE6tcLaXE9nV8j0Nt8Zubh8YFff5Pl1zcNA/iYCaK07jzplys/fVdky2vTwWemA93aVSIWA6kheCIBc+DhkTLiUqFrqSUShjC2QtSCQm/mLdVV2lhh9Grf5NnbbNZWW6dFO+nL7K1fB4somaVmAcafu6tDGLrJcr2A8Ti6hYF4qHUtKFeFBA32eAOMxMWAtFzpDuzUG4lSutROpkYMA59mN2WB4rtKAY0Q33J1Uzbl/grY7PBIGv96REofShBepRZCKb9TVBRO7IV8vapqMyhnSKrajkwuVrJa0nVTqnU64BTqmvN8kYEpbFYKYUu6roEdYGrFbY3PVllmlMwhabsooe2sSq/9jbYp19ObcJVmbFGWK09EdGGbwKZvvCZQVgeaQLEhXI9a12XzeTa1UoIW0tsU9KDubY0rjhgnXxCdkGlGO0gVO8sa9DV4VJ5hA8o6XAE8BOUB3oo/faIYlODCiNfjvzkdzvIqY5bUMOzxWriRMKWrBkyyZ1HcVGsw246jeJ6C3RtbC0uBJ8Jkiu+Cui73lAe6h9vgnig/9suLBnxr7JZ48WCcyg0zWWRgjA210To2cZSTyUR4eJEAzxar5n2k6hDLZBK9yN5Ez4UBGp2BqRSVQt+TQIHwbaVrm6BV1GrPE2vbc4dlS/CTBi3dPldWCekYjAihB0h/VXYNPKneqx616yCxEsfLmPUBQ/nyok/cJZ/Fo8uRikPRsIs04ZGmm2ZRJNkS9lncRKfgjjxw96zD0bNfBe0xrLDo5XfnyT9++v785Rm4vz+d/33vC8XWW3TzWlmA82JoIhWyqdS7v/6kEO8JZ+Whp4CiH8GC/XUw44qPW1jO4BjPWc2zZwaFZX0IxSegfWIRe4ClBGt12aT5sg6BgjTIrxMZfghyHJXZf8Sd1T+nb1LJpOM3ZT4bHoxO4g3TaIdiF3fnYOr1FQwnPIqDcfT5SIDeNM3qeDLp0+IL26LGJpreP058B/eyXQyx9AviALSN2/RsazWsnIqW2aqaKl8MBT6QGeTVPv4H/xayJihbE61liLwa2TaWgJrjEe2eVAb1cAiN7AvH34pJCF2AFGOuBGkhtZuIsaAt1DBchpxdFEKcqOTs5Y//8/LHix0hDzugDp4RCUUlogWchvrTn9N3ZiNFgP0dgwkRJV9/f3Z+aYp+fPmPn16enSc//fjN5Q47MsCodyIlo9UKkEDKEEmk1aPj+G2x9aKcpmDsCHNyYsOqtG9dWvshQNo6YVbXt7rR0FoJYHucifBE9L21dBIRaKiTBHAPhUU1qrNifnwMpss0q2ttnQxpNF+FV9RU7pq4Sj4TZwomcijrUaQ+EQbY0HY3GikdGGgsCYBR6M7rckmaEhuPKcqQ6INdqhVqUBhpvWS3jbeivQy0VDnw+P+sMLPJUTsh7GsK3t0ksYahl9HWgQOsiimkyxjsATb5xarApRWP47HHO37MlV4TutxjxCTeZ0Nw6TQ+o2o/zJfNiJwy2Oqn0UHAUYgoMuqeIT4PHQfYAqPwBztRKogeOK1PGh4zre+Bznq/YXS0vgc64ogHx8vqe+CToe5CBCAlwlh8OTjYE6c/BjEJaV/i5hZJsMM2sEMGdtQGdsTAHreBPUYwDfWkDeoJQNHByeh23cJ5Ffu+VNpp6MgUC9uIfe9gxAw2B0CsZKqW8bhX1qI9U1Pl4TYeR16NjPOEalSI55LovV2jgKLBoFsBgfrHeIHyf3dNWCHy+GMjDpdawes4gw9MYhAKei6DZxr5i29+TJ5/9fzrl/uggyYkc0Jt1TABn8xzGEP2DurAGqftR9LgWLyG7TTiVWCqfv755+MIhZxsNFKuFa74uaDu27zBs0qEwXM2tDFhd07KJeyFQ6Tv5T/PX3539s3336FImlPuWuRxTIr8qkqr9xOgnmrQicU9wX/2VzcrvXPLUELtHKEJsl/klQgk87Go6FY+zzAUJwEsZ0nww3YKyJfZ2+iVLRnqftmej/ZGhIaQaJ/XCX627qK2fYShnfwzUTbQyxfJ/35z/rWy5cFbaMqifJtVfRqgqf5uUaC9pcx5M9Omf0CqCfgBbLCLWFXwpATZ+waQDyrOqWVSr4T1kmzeCoXZ3ZVp7x+DykWkwEV3sIR1j3Sbhrb4bTDo2ssHuyzkHbmwPCCu24Q0kdL5jo8f1lncW2/Ra8eg1k6UXzcMd0lUH7pMjJjfSes5hKBH5RpeXjj9krpxnMPiqMidFH665E4KiXqEm7GwiNNYHBUp7rUYLPI0Se1ntiU5Ruoyd3jei4NDHzRFXf2TzBWnuTqG6mxt805UY/fY3J0t71hds0wlHJh9jceWFZA0cvUaDBm5aiUXV852CG31Ibm3gu3xuYKVDawl5K1jaiSdONCH7dCHPvRRO/SRD/24HfqxD/1EQbeYUApcnn74ek2dihCsaGPp+emysQa72l/YAN1puFpBBDehew1t9iMsMnQQurH1cCFAmrR30DLEvt4DmnTKMWjB1NdxgL3H+AThAfb2GewEqpPCTqdQnyZeuo3FgVuYFH6YaFoSL0W1+3fwUlD41JmYnuNoq7Mzb5o0u2zC2MOliykLwdGOMkFIxGV3DnaMlaBDEuiQWDvKloJGiBzQRMKGoz4EnxP3QQwi3Qd3kJph+EFaVfUwop247cl5gyAmn9W+E2WDXbrThIN2B80krCXYDXapGFNHvMsftWc8ioyly1Z+h5jF+zX88gj0eCblrMLYYzqDxYy8QnvX7MdXOJ1dlBAY/ekUU5tQ+YDwoVCrCkdRyzSXLkWtEmE8w0WmwIiWbQa0ypIRpnMLdg0CBB46HWAWTYsRKRJsXDMQHbcWeMy5CfoOmFETDPx4aTe6OVjhMo9T+Il1M3uO2lRFZQfllXRnhng2jSaODXEkICRAjPAxiQOLvrNdEDzzFR3WwJSP1WL30kfV97GqthZXpPNLdYkG4a6STJXiHNKQ3H5XkKzQhaQme8TASY3bSGpIB70odCGpUc7Rkxq3kZMV5hnxLry2vB1wVayhPXNbgbvlGp5oDZnkQfWIBmJZrwhjCzyQQxfk0AM5ckGOPJDHLshjD+SJC/JEg9j0HqyX30hzETkcmMb41YipjKlrERXfbN0Vq7sidTTYqrhoS+xEklQdOYWqwJ1qmeZEZxlKXCCVKkWhsEiD0aQwhNHfzXKjiV9iqZkCF4XJHqN4VKELq3OuKKgo04AsaQyhTIFhNM0dE7zWBRrCbE8awhS4TNKpk5RLssyF1GmUFFKWuZAqpZICYpE3iSK5kk1iFlZ2Qqn76g6LjYCRmwIyeGgLBMiIOLJS0YvzWpVLxY4mcVRb3ByagJNNYl9llSUwGGH/78cTfRwqNcg44vuLtmncDUl8z6Y3ZWTJPCGb1NUaFm0FhEMbzATFVI5s2Zi9TW19e09Ft8kirV6v8Rh5VWXXSZWJHE+5pw0GZIMTrJxEH47GtxOVRSeLvvxkb2//0bO9vae/vGp+GT47flU9e7W8iF41l49Gf53UsYIdjVuQRhSdB63GQycKBpYtZ8m0yNKlHtUsz6Lhz2AcQpU8X5fDlHeAnBSaySR6LvLfZBy+AE6YJA4b/Cd2O3wTVtkQtmZQO6PhYvbZkJx/yiH1uhU01gJVgzEMIrT3VGdBVBWQ/p7G+UajkbFsjTjamLbsQpOnIUWkX4KbfJANTkp8EOvB2qN1fUHLWMJ4PKGs8Dyr/54XDYa8HQuceQ4bkH1VNTlMYjsqLQK/8lJUK8/0y1hcp28wRM4AqqxZV0vwS+Xik6U8i6YFxnOuYGI6DngMNkaSlPwXeeWA204M7p6Tfr85Z1N+nxm/JRpPM/A3mfNbfWh4K5WFew95Iyc+6GQP9H47fGQsh4nQzu8Y3DpUHbHNFjG3Q1w09trIpb14G0gpQSS1U6ZPAFWmifK8R5Hk8QnNdom2cdsJziSEdA4sB9GMTHWU1rD/grKKnBQXNySjrvGa+0pC1xEijL4Tw70Q6T8aVlbUb/NGdE15Z1aU2nuP5bcpprzJiziqZLDGyIFoDLOkBWG4m4LgXI0+KOGsm2qZNth6ulgNwWq72EEkOyheV/rz6OTWaI4r4MfrE9ppMW+263M3hdUwb6IvoVB86kRfpctZuTA91DewmxZS/upQs9vW1Dv3ahQqkUMzj1DD0nJkqtojHXwT1SZHOv6qKCIVWGEQ+vzNJB5JYyFZL+ubfG5Yg8D0oF/IlrrR5S4bclPsUuc6FbCHi/iMCBDqFLQksHCsAItKIb1pI0a9ZYoWADoMcuNHu3OhVPRmR82kWFXR9pHwlrtznrQJLKJgJh6hDP+WWJluI5ZMvKmNue5n2sn7fW3t2CVA00YeC0eDUAN5YkztP4+Xib1N7pb6rL3wrmpiQ52E58WcDFD44NjiOMYd9eQjtzP1xXgnM5mrOPQ6dK+IyvN9PNeXxmUHICfNqwfStEx/ZLiHV+z9rEmztSZ6a01s/JturUIfn5egpHWAlFUPKRNIoJz1/ZScoCdMjbuH0reBTUy0UDkw/paj6smKHWgiVJhTkAJrFfmnqMCsCF5DCRHq0BtA9MGa3YFFjpYW00/EprP9iLdM4MMJqxLRJG5EsWh0aA0Q58EgIwoPj0a4sWVmheR0LdegX2U1J1bcbDVKemOKA8VLsxfcW64tlW52A7cz70BQTC4wykuGD0CUEmBk/0VAAAIizaT5EzM9WjK40cQ3ncFGOfzNxGqD4AysD/gHEB3XLf2DCI91cfuJD7fwbgMipY1Evfd1OzRt/lhfd6av/3FH58LzLbD4g6vxbZK73HM4xf5zAIr0VI09ka1cis2+7VuV/AWBS4dYRQR3hDCRM1/qrZzjHpMmrgbQI0IdID/bOz4+hbJESUfAqFds2vZagWOocru4ncI/7dm+9mxa4cFb/btYZaRzx3tj4mTBvNfFxJWaq6zQ2EdBz81ZbujESYzOTRUrWe4C9SVM7ISyqMMoMwCeXcZXENlCdZPwLtqWZOBhPHHxbdp6eX5B1BIfU5tFcFzexN11ewhmmuCxXeTvErIOJty73QRazzgwBAnV5MqO0FWnpzuPdqQN8SGQDNOVf+ImrZBGARJc9rgDocAmRdv2zV/TkcEQOygHmYcLxXy4m58enkS7+ZeBiwPsNZ7Lk938r3/lTGlXroOYq9VWrQqNzDGL22XElJZLj2zkpe7U9f00l42vJniSs0l18eiMpkCZy+Kr48TwXVgDeWEZxqCgOWuqeeDRweQYeV3mGsXZvS775CN9aLPNhPcbWI9yd6Xyu+3efcete+t9+06bNr6/wRfYxm17w55N7+PfgVX+3P17cUdFVP+Muv3uUbeWYAFZ6SuNSW17dyest81pYnSsb1TcZsKW85Lvd/yuUMtpkQIS1HyzEHe8GUqSETgjt2bMJCrnHSUlUHgqssB3Hh+s3u1gJqYL4ePT/j5HaEtbMXoXe3h2yzyvrG5PlIXNQExD8e0o2nwhqTO+EYhquCSieRSiUoWFcFnp1RcAM8GekRt47D2ATrRbDuaW5FK4RAs5MtRGnFAQ/uutaKXYxtHfDg7EPyOWVBAkI8i7+1IT5FyYqLt1FZAyD31f3ov7j54Cdl7Iu3R5co/Z2U6E5Mzp44bOeeu9ODYNWcPdY8gPsHgc+cB7iIG9vvVVQTlMmhazIwjx0juk4E129nshtYFrzPKqCisYik3ioSqgdOde/Rirn3PB7YM+drjjhYN7T5tFencNp/PWxDs8WbX3NK+/La+vs9lQPD/0SUdem26jr8SQxSavc9rnYFR+295T2P0XqXknqUnfQWVaTNeY9Ohv1+LC5zhyyht8hFWkCctM+g4SxVP1PdLu2qRXj0PnCjgpVtFwOC/KFMxnTmG9yqZ5WjD9o8ruyxSD+rdki3jCG+xvYUZiSGwv3q/g82w4bJm0aC9q5ckk3GT06BA3g4PRfvyXuGU+LBPpjAwobWyqyAUOJu2tPLFdNeIxgLa52jDv6B20VR1HrXJ+RymVpLbKaMegqwyz6hL17Dcdvkz2Rg5wYvVVohZSTDOHGlGp1N+QJhY5TxLLZ0XU0wpQ5oqXevkwoj6ohwOdM/WUG/zNVzC31/Uw9A5XCHn48cOReAGus9P9KN7fj/35VTRro5zcnHGYEX5deRNL2LV6zpkWhHflD+upH5taKWhjFh/NiZbjyaNT8Uc4dtF/pfizMe9l0emjif2pAJODKvfGawGYqzfCaDTM7MkG5GH9ShuedLtBV5eWvaeZ2tbsZSAhK791pBeXblBYhHgxfmN/oGCTVeF2PFZxonZdItthP+ocfLRVG2WjjEbkQkBzs15c/RZUi462JVs2MnRrskfB0C/GqMCs1hSaUMYGL8fbR+wrJf28QoYhAIBBNNcts6pKxdMI4bEvXFvIVoAHv4NgbSNX96D44YSKXQfa9FYyO/8CmfEVH5WoPlpPxfZ6hdOm5hinD2Z76LOdTiW9kCOfPBvRRCjLgovLbVPMNcKLHRKt3+EOFN+Zom+zFBbQsbpdVMNyitQgyD5FZvGR4tiGX5aS0jG5zpZZlRZ2bYoO9G/T4Bn9mzQvVIHdDTEw1xbUVXKnMAsO/LTMm/oMMHdOx+gD7cKQ8vAdsaEIm1Z32WAcPPnXOhX2im+m6hphqBLjQHPJRQDuCo7CrDPOQTHAp9IIc1uqRFZFmGSGXPR4wRwWMzjI2mkQlRMHwyh6FKHnczQ60UjYsNVpk8tw08eByyXEwGUNFXmGD6Z9B7pCXXt/AUV4F3VItb0Aeg6rzD6jZuBcQw1/70KwM70qqH0Lai6r3khPl/QL7kw+l28Kkl5Y5y9SMZMagXG24r+ksau8vsveisC6bvclezNA/xYHOmKH5EWvgf/zVO1evPZLW3/USgAkB770qZbb/0yWxbh3GGptnFn1oh0/eDQ8EqJhDsBW0tIwmkJuLm3VbR5j51v+AQcySMPWiH0s8fjogIfzQj2d0qzYFmKODrzQW5g1W1MdQOOTHezLoTtMj084/08LB8n93noIEkVsE8nNibL2XPsfFvroqVkhH12u//O9/PBN55YAvqUzOM5UTiVlJa9R6US8kBybayAvkSPIXKdI3zoesBcvH5hf3RunDXsRq0nTgibTRgaBAl5nrn6hUuYh9tgnUIAOtv3JItKrSoOntRdxvb5q8Ce2oKl4AZ/X2q1d7L0UGQwifEZ8D3ZvdvnHkUMh4yA1YCn3VPx4uEWAXZofrfH6OwTs8ecI1N7Hh0D3voHlLpZ+v30IO4j73gFsfBjpBwwhiVjjFuHs0AkNGx4LLus4Mp+8tgnVJ35SDp3DO7+U99//wM5B1Mks6ViuytV6Zb3RnvDGET1hhEo39dck13Get/WbN820Jj2OuwbGam+7J5oHWcSq53MeAnDEjwOIDaq9dZi0kFR7aNvG1b4HhGKK9IcfHY0nQivuqg+DXo4pQg8RzW7boGUZIvmzqoE/PiIV9qet1VtawdY67MaR0HjbpuUQbAky7lupUBgwAUfit4HGfCLEm1vh4Uq95sNjqjr+oGs3exgoZ5PcOQPMEr3St8covFyqrJWAJ2tvHBIyI/p8bLzSNiQ6wIi4+UD9p2C83LcEOXeCtuK4tb00hRjF3hojrXmv/txcxOFew1JP27nCLt4G6W4if+KNNFGD6WiiLD/SRr0tP4tb2xiISz+STnda9lBIyA/WOXGkSvwcd794m37j/lcMvCkL9DeMwN2/xz9DcduH4rSQ2tC4uQXk6xzKbke1+R6HbqMOMlSP4r1Cqj5l/ZEPcGQg3NNDsjBbjhdNU34u5DV0jo1MM7IsY77hUCWo+i3w0UWK2+QbGkCm8wY+47jGE1/JUbR4zBAzqHS9/+Rl1yF4oBVHHjpVJ8D2Dg7bwQdm2+b1yYEPkRwYGJ1kYiFUiaVTJcrYF/bE17EjhN/hG3xk7PB9PDBClRKTQT3q+M5gUAkipJa9Gwi7h3ww0AK4SZKCzf67s+F6DO7a0YmY9GyW6RulwZi1qLdTJ7QlwgebyFoLbbRZi7RZbWea3IDpxAVUqul1BQoYnxYdarZP1P/xODI/b3YqHs3u87oc5lR8atWEbNih0q3YGB2mNI8t4DBShxIgUy2UOa8XRVZq2IGcWlmszOAKxHwFwoB5bFgcCLhik0D5mB2pqneb1XUppqLt1dS2i5NV/gYPSvjNenYbVjhi5jEqfoFVvtG0ss/lDo4j590z/ua5tBTZ++MKh/qZ1d44BDRHkahl0BeFBHdw2MXRE4tpwBBpFdKXFgXvEIN3pRJ1RaofHtEC1IOLiaqVfph0CxeT1n04MFqxTyt64FfgDHudFVvMn4C2168dkTYS3fZIg7n+u1pfkUu2oUcCyONvubz/9oBPwJFn4HbzwENw/OE7eS/UhF3skz+idfgXUfUYe9z2vG17rkjjGPV5HoFdGAR+HTh38GiRuswHRU/cR7LU6x2/zYVc2at96IOdR9KsNvNQ4IdASod5I5A8eGOwGv6HL9reUnVuh68aaxL7SeUDvExo8bQIZrdktoiml267hXA6V5CdC8gCi9jbgPTb/wdYTg8L")));
ensure_file_written(__DIR__ . "/extension/so_theme/catalog/model/module/so_listing_tabs.php", gzuncompress(base64_decode("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")));
echo "✔ Deployed SO Listing Tabs Product Feature selector (admin & catalog).<br/>";

// Clear template cache, SO listing tabs cache, and minify CSS cache
$cache_dirs_purge = [
    __DIR__ . '/storage/cache/template/',
    __DIR__ . '/system/storage/cache/template/',
    __DIR__ . '/system/storage/cache/so/ListingTabs/',
    __DIR__ . '/storage/cache/so/ListingTabs/',
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

// Purge Varnish Cache
$purge_targets = [
    'https://www.magicalsingingbowls.com/',
    'http://127.0.0.1:6081/',
    'http://127.0.0.1/'
];
$purge_header_sets = [
    ["Host: www.magicalsingingbowls.com", "X-Cache-Tags: d0a4"],
    ["Host: www.magicalsingingbowls.com", "X-Purge-Method: regex", "X-Purge-Regex: .*"],
    ["Host: www.magicalsingingbowls.com"]
];
foreach ($purge_targets as $pt) {
    foreach ($purge_header_sets as $ph) {
        $ch = curl_init($pt);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $ph);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        @curl_exec($ch);
        @curl_close($ch);
    }
}
if (function_exists('exec')) {
    @exec('clpctl varnish-cache:purge --purge=all 2>&1');
    @exec('/usr/bin/clpctl varnish-cache:purge --purge=all 2>&1');
    @exec('/usr/local/bin/clpctl varnish-cache:purge --purge=all 2>&1');
}
echo "✔ Varnish cache purged.<br/>";

echo "<h2>Done! Remote database updated and cache cleared.</h2>";

