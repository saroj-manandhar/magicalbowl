<?php
namespace Opencart\Admin\Model\Extension\Tmd\Other;

/**
 * TMD Import Model for OpenCart 4
 */
class Import extends \Opencart\System\Engine\Model {

	private ?bool $has_special_table = null;
	private ?string $recommended_table = null;

	public function hasSpecialTable(): bool {
		if ($this->has_special_table === null) {
			$this->has_special_table = ($this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_special'")->num_rows > 0);
		}
		return $this->has_special_table;
	}

	public function getRecommendedTable(): ?string {
		if ($this->recommended_table === null) {
			if ($this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_recomended'")->num_rows > 0) {
				$this->recommended_table = 'product_recomended';
			} elseif ($this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_recommended'")->num_rows > 0) {
				$this->recommended_table = 'product_recommended';
			} else {
				$this->recommended_table = '';
			}
		}
		return $this->recommended_table ?: null;
	}

	public function getProductByModel(string $model): int {
		$query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE `model` = '" . $this->db->escape(trim($model)) . "' LIMIT 1");
		if ($query->num_rows) {
			return (int)$query->row['product_id'];
		}
		return 0;
	}

	public function getCategoryByPath(string $path, int $parent_id = 0, int $store_id = 0, int $language_id = 1): int {
		$parts = explode('>', $path);
		$current_parent_id = $parent_id;

		foreach ($parts as $name) {
			$name = trim($name);
			if ($name === '') continue;

			$query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c 
									  LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id) 
									  WHERE cd.name = '" . $this->db->escape($name) . "' 
									  AND c.parent_id = '" . (int)$current_parent_id . "' 
									  AND cd.language_id = '" . (int)$language_id . "' LIMIT 1");

			if ($query->num_rows) {
				$current_parent_id = (int)$query->row['category_id'];
			} else {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET parent_id = '" . (int)$current_parent_id . "', `top` = '" . ($current_parent_id == 0 ? 1 : 0) . "', `column` = 1, sort_order = 0, status = 1, date_added = NOW(), date_modified = NOW()");
				$category_id = $this->db->getLastId();

				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET category_id = '" . (int)$category_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "', description = '', meta_title = '" . $this->db->escape($name) . "', meta_description = '', meta_keyword = ''");

				$level = 0;
				$path_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$current_parent_id . "' ORDER BY `level` ASC");
				foreach ($path_query->rows as $result) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id . "', path_id = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");
					$level++;
				}
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id . "', path_id = '" . (int)$category_id . "', `level` = '" . (int)$level . "'");

				$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "category_to_store` SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "'");

				$current_parent_id = $category_id;
			}
		}

		return $current_parent_id;
	}

	public function getManufacturer(string $name, int $store_id = 0): int {
		$name = trim($name);
		if ($name === '') return 0;

		$query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE `name` = '" . $this->db->escape($name) . "' LIMIT 1");
		if ($query->num_rows) {
			return (int)$query->row['manufacturer_id'];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name = '" . $this->db->escape($name) . "', sort_order = 0");
		$manufacturer_id = $this->db->getLastId();
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "manufacturer_to_store` SET manufacturer_id = '" . (int)$manufacturer_id . "', store_id = '" . (int)$store_id . "'");

		return $manufacturer_id;
	}

	public function getFilterId(string $group_name, string $filter_name, int $language_id = 1): int {
		$group_name = trim($group_name);
		$filter_name = trim($filter_name);
		if ($group_name === '' || $filter_name === '') return 0;

		$fg_query = $this->db->query("SELECT filter_group_id FROM `" . DB_PREFIX . "filter_group_description` WHERE `name` = '" . $this->db->escape($group_name) . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");
		if ($fg_query->num_rows) {
			$filter_group_id = (int)$fg_query->row['filter_group_id'];
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_group` SET sort_order = 0");
			$filter_group_id = $this->db->getLastId();
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_group_description` SET filter_group_id = '" . (int)$filter_group_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($group_name) . "'");
		}

		$f_query = $this->db->query("SELECT f.filter_id FROM `" . DB_PREFIX . "filter` f 
									LEFT JOIN `" . DB_PREFIX . "filter_description` fd ON (f.filter_id = fd.filter_id) 
									WHERE f.filter_group_id = '" . (int)$filter_group_id . "' 
									AND fd.name = '" . $this->db->escape($filter_name) . "' 
									AND fd.language_id = '" . (int)$language_id . "' LIMIT 1");

		if ($f_query->num_rows) {
			return (int)$f_query->row['filter_id'];
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter` SET filter_group_id = '" . (int)$filter_group_id . "', sort_order = 0");
			$filter_id = $this->db->getLastId();
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_description` SET filter_id = '" . (int)$filter_id . "', language_id = '" . (int)$language_id . "', filter_group_id = '" . (int)$filter_group_id . "', name = '" . $this->db->escape($filter_name) . "'");
			return $filter_id;
		}
	}

	public function importProduct(array $row, int $default_language_id = 1, int $default_store_id = 0): array {
		$product_id_in  = isset($row[0]) && is_numeric($row[0]) && (int)$row[0] > 0 ? (int)$row[0] : 0;
		$model          = isset($row[4]) ? trim((string)$row[4]) : '';
		$sku            = isset($row[5]) ? trim((string)$row[5]) : '';
		$upc            = isset($row[6]) ? trim((string)$row[6]) : '';
		$ean            = isset($row[7]) ? trim((string)$row[7]) : '';
		$jan            = isset($row[8]) ? trim((string)$row[8]) : '';
		$isbn           = isset($row[9]) ? trim((string)$row[9]) : '';
		$mpn            = isset($row[10]) ? trim((string)$row[10]) : '';
		$location       = isset($row[11]) ? trim((string)$row[11]) : '';
		$name           = isset($row[12]) ? trim((string)$row[12]) : ($model ?: 'Product');
		$meta_desc      = isset($row[13]) ? trim((string)$row[13]) : '';
		$meta_keywords  = isset($row[14]) ? trim((string)$row[14]) : '';
		$description    = isset($row[15]) ? trim((string)$row[15]) : '';
		$tags           = isset($row[16]) ? trim((string)$row[16]) : '';
		$price          = isset($row[17]) ? (float)$row[17] : 0.00;
		$quantity       = isset($row[18]) && is_numeric($row[18]) ? (int)$row[18] : 100;
		$minimum        = isset($row[19]) && is_numeric($row[19]) ? (int)$row[19] : 1;
		$subtract       = isset($row[20]) && is_numeric($row[20]) ? (int)$row[20] : 1;
		$stock_status_id= isset($row[21]) && is_numeric($row[21]) ? (int)$row[21] : 7;
		$shipping       = isset($row[22]) && is_numeric($row[22]) ? (int)$row[22] : 1;
		$seo_keyword    = isset($row[23]) ? trim((string)$row[23]) : '';
		$image          = isset($row[24]) ? trim((string)$row[24]) : '';
		$date_avail     = isset($row[25]) && !empty($row[25]) ? date('Y-m-d', strtotime($row[25])) : date('Y-m-d');
		$length_class_id= isset($row[26]) && is_numeric($row[26]) ? (int)$row[26] : 1;
		$length         = isset($row[27]) ? (float)$row[27] : 0.00;
		$width          = isset($row[28]) ? (float)$row[28] : 0.00;
		$height         = isset($row[29]) ? (float)$row[29] : 0.00;
		$weight         = isset($row[30]) ? (float)$row[30] : 0.00;
		$weight_class_id= isset($row[31]) && is_numeric($row[31]) ? (int)$row[31] : 2;
		$status         = isset($row[32]) && is_numeric($row[32]) ? (int)$row[32] : 1;
		$sort_order     = isset($row[33]) && is_numeric($row[33]) ? (int)$row[33] : 0;
		$manufacturer_id= isset($row[34]) && is_numeric($row[34]) && (int)$row[34] > 0 ? (int)$row[34] : 0;
		$manufacturer_str=isset($row[35]) ? trim((string)$row[35]) : '';
		$cat_ids_str    = isset($row[36]) ? trim((string)$row[36]) : '';
		$categories_str = isset($row[37]) ? trim((string)$row[37]) : '';
		$rel_models_str = isset($row[39]) ? trim((string)$row[39]) : '';
		$extra_imgs_str = isset($row[42]) ? trim((string)$row[42]) : '';
		$specials_str   = isset($row[43]) ? trim((string)$row[43]) : '';
		$tax_class_id   = isset($row[44]) && is_numeric($row[44]) ? (int)$row[44] : 0;
		$filter_names_str=isset($row[46]) ? trim((string)$row[46]) : '';
		$discounts_str  = isset($row[48]) ? trim((string)$row[48]) : '';
		$meta_title     = isset($row[50]) && !empty($row[50]) ? trim((string)$row[50]) : $name;
		$diameter       = isset($row[54]) ? trim((string)$row[54]) : '';
		$rec_models_str = isset($row[56]) ? trim((string)$row[56]) : '';

		if (!$manufacturer_id && !empty($manufacturer_str)) {
			$manufacturer_id = $this->getManufacturer($manufacturer_str, $default_store_id);
		}

		$product_id = 0;
		if ($product_id_in > 0) {
			$chk = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$product_id_in . "' LIMIT 1");
			if ($chk->num_rows) {
				$product_id = (int)$chk->row['product_id'];
			}
		}
		if (!$product_id && !empty($model)) {
			$product_id = $this->getProductByModel($model);
		}

		$is_new = ($product_id == 0);

		if ($is_new) {
			$sql = "INSERT INTO `" . DB_PREFIX . "product` SET 
					model = '" . $this->db->escape($model) . "',
					sku = '" . $this->db->escape($sku) . "',
					upc = '" . $this->db->escape($upc) . "',
					ean = '" . $this->db->escape($ean) . "',
					jan = '" . $this->db->escape($jan) . "',
					isbn = '" . $this->db->escape($isbn) . "',
					mpn = '" . $this->db->escape($mpn) . "',
					location = '" . $this->db->escape($location) . "',
					quantity = '" . (int)$quantity . "',
					minimum = '" . (int)$minimum . "',
					subtract = '" . (int)$subtract . "',
					stock_status_id = '" . (int)$stock_status_id . "',
					date_available = '" . $this->db->escape($date_avail) . "',
					manufacturer_id = '" . (int)$manufacturer_id . "',
					shipping = '" . (int)$shipping . "',
					price = '" . (float)$price . "',
					points = 0,
					weight = '" . (float)$weight . "',
					weight_class_id = '" . (int)$weight_class_id . "',
					length = '" . (float)$length . "',
					width = '" . (float)$width . "',
					height = '" . (float)$height . "',
					length_class_id = '" . (int)$length_class_id . "',
					status = '" . (int)$status . "',
					tax_class_id = '" . (int)$tax_class_id . "',
					sort_order = '" . (int)$sort_order . "',
					date_added = NOW(),
					date_modified = NOW()";

			if (!empty($image)) {
				$sql .= ", image = '" . $this->db->escape($image) . "'";
			}

			$this->db->query($sql);
			$product_id = $this->db->getLastId();
		} else {
			$sql = "UPDATE `" . DB_PREFIX . "product` SET 
					model = '" . $this->db->escape($model) . "',
					sku = '" . $this->db->escape($sku) . "',
					upc = '" . $this->db->escape($upc) . "',
					ean = '" . $this->db->escape($ean) . "',
					jan = '" . $this->db->escape($jan) . "',
					isbn = '" . $this->db->escape($isbn) . "',
					mpn = '" . $this->db->escape($mpn) . "',
					location = '" . $this->db->escape($location) . "',
					quantity = '" . (int)$quantity . "',
					minimum = '" . (int)$minimum . "',
					subtract = '" . (int)$subtract . "',
					stock_status_id = '" . (int)$stock_status_id . "',
					date_available = '" . $this->db->escape($date_avail) . "',
					manufacturer_id = '" . (int)$manufacturer_id . "',
					shipping = '" . (int)$shipping . "',
					price = '" . (float)$price . "',
					weight = '" . (float)$weight . "',
					weight_class_id = '" . (int)$weight_class_id . "',
					length = '" . (float)$length . "',
					width = '" . (float)$width . "',
					height = '" . (float)$height . "',
					length_class_id = '" . (int)$length_class_id . "',
					status = '" . (int)$status . "',
					tax_class_id = '" . (int)$tax_class_id . "',
					sort_order = '" . (int)$sort_order . "',
					date_modified = NOW()";

			if (!empty($image)) {
				$sql .= ", image = '" . $this->db->escape($image) . "'";
			}

			$sql .= " WHERE product_id = '" . (int)$product_id . "'";
			$this->db->query($sql);
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_description` WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$default_language_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET 
						  product_id = '" . (int)$product_id . "', 
						  language_id = '" . (int)$default_language_id . "', 
						  name = '" . $this->db->escape($name) . "', 
						  description = '" . $this->db->escape($description) . "', 
						  tag = '" . $this->db->escape($tags) . "', 
						  meta_title = '" . $this->db->escape($meta_title) . "', 
						  meta_description = '" . $this->db->escape($meta_desc) . "', 
						  meta_keyword = '" . $this->db->escape($meta_keywords) . "', 
						  diameter = '" . $this->db->escape($diameter) . "'");

		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$default_store_id . "'");

		$category_ids = [];
		if (!empty($cat_ids_str)) {
			foreach (explode(';', $cat_ids_str) as $cid) {
				$cid = (int)trim($cid);
				if ($cid > 0) $category_ids[] = $cid;
			}
		}
		if (!empty($categories_str)) {
			foreach (explode(';', $categories_str) as $cpath) {
				$cpath = trim($cpath);
				if (!empty($cpath)) {
					$cid = $this->getCategoryByPath($cpath, 0, $default_store_id, $default_language_id);
					if ($cid > 0) $category_ids[] = $cid;
				}
			}
		}
		$category_ids = array_unique($category_ids);
		if (!empty($category_ids)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product_id . "'");
			foreach ($category_ids as $cid) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$cid . "'");
			}
		}

		if (!empty($extra_imgs_str)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . (int)$product_id . "'");
			$img_order = 1;
			foreach (explode(';', $extra_imgs_str) as $img_file) {
				$img_file = trim($img_file);
				if (!empty($img_file)) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($img_file) . "', sort_order = '" . (int)$img_order . "'");
					$img_order++;
				}
			}
		}

		if (!empty($filter_names_str)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_filter` WHERE product_id = '" . (int)$product_id . "'");
			foreach (explode(';', $filter_names_str) as $f_item) {
				$f_item = trim($f_item);
				if (empty($f_item)) continue;
				if (strpos($f_item, '=') !== false) {
					list($fg_name, $rest) = explode('=', $f_item, 2);
					$f_name_parts = explode(':', $rest);
					$f_name = $f_name_parts[0];
					$fid = $this->getFilterId($fg_name, $f_name, $default_language_id);
					if ($fid > 0) {
						$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_filter` SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$fid . "'");
					}
				}
			}
		}

		if (!empty($specials_str)) {
			if ($this->hasSpecialTable()) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . (int)$product_id . "'");
				foreach (explode(';', $specials_str) as $sp_item) {
					$sp_parts = explode(':', trim($sp_item));
					if (count($sp_parts) >= 4) {
						$cust_grp_id = (int)$sp_parts[0];
						$sp_start    = trim($sp_parts[1]);
						$sp_end      = trim($sp_parts[2]);
						$sp_price    = (float)$sp_parts[3];
						$this->db->query("INSERT INTO `" . DB_PREFIX . "product_special` SET 
										  product_id = '" . (int)$product_id . "', 
										  customer_group_id = '" . (int)$cust_grp_id . "', 
										  priority = 1, 
										  price = '" . (float)$sp_price . "', 
										  date_start = '" . $this->db->escape($sp_start) . "', 
										  date_end = '" . $this->db->escape($sp_end) . "'");
					}
				}
			} else {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . (int)$product_id . "' AND `special` = '1'");
				foreach (explode(';', $specials_str) as $sp_item) {
					$sp_parts = explode(':', trim($sp_item));
					if (count($sp_parts) >= 4) {
						$cust_grp_id = (int)$sp_parts[0];
						$sp_start    = trim($sp_parts[1]);
						$sp_end      = trim($sp_parts[2]);
						$sp_price    = (float)$sp_parts[3];
						$this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET 
										  product_id = '" . (int)$product_id . "', 
										  customer_group_id = '" . (int)$cust_grp_id . "', 
										  quantity = 1, 
										  priority = 1, 
										  price = '" . (float)$sp_price . "', 
										  type = 'F', 
										  special = 1, 
										  date_start = '" . $this->db->escape($sp_start) . "', 
										  date_end = '" . $this->db->escape($sp_end) . "'");
					}
				}
			}
		}

		if (!empty($discounts_str)) {
			if ($this->hasSpecialTable()) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . (int)$product_id . "'");
			} else {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . (int)$product_id . "' AND `special` = '0'");
			}
			foreach (explode(';', $discounts_str) as $disc_item) {
				$disc_item = trim($disc_item);
				if (empty($disc_item)) continue;
				$d_parts = explode(':', $disc_item);
				if (count($d_parts) >= 4) {
					$cust_grp_id = (int)$d_parts[0];
					$qty         = (int)$d_parts[1];
					$priority    = (int)$d_parts[2];
					$rest        = $d_parts[3];
					$d_price     = 0.0;
					$d_start     = '0000-00-00';
					$d_end       = '0000-00-00';

					if (preg_match('/^([\d.]+)-(\d{4}-\d{2}-\d{2})-(\d{4}-\d{2}-\d{2})$/', $rest, $m)) {
						$d_price = (float)$m[1];
						$d_start = $m[2];
						$d_end   = $m[3];
					} else {
						$sub_parts = explode('-', $rest);
						if (count($sub_parts) >= 7) {
							$d_price = (float)$sub_parts[0];
							$d_start = $sub_parts[1] . '-' . $sub_parts[2] . '-' . $sub_parts[3];
							$d_end   = $sub_parts[4] . '-' . $sub_parts[5] . '-' . $sub_parts[6];
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
										  product_id = '" . (int)$product_id . "', 
										  customer_group_id = '" . (int)$cust_grp_id . "', 
										  quantity = '" . (int)$qty . "', 
										  priority = '" . (int)$priority . "', 
										  price = '" . (float)$d_price . "', 
										  date_start = '" . $this->db->escape($d_start) . "', 
										  date_end = '" . $this->db->escape($d_end) . "'");
					} else {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET 
										  product_id = '" . (int)$product_id . "', 
										  customer_group_id = '" . (int)$cust_grp_id . "', 
										  quantity = '" . (int)$qty . "', 
										  priority = '" . (int)$priority . "', 
										  price = '" . (float)$d_price . "', 
										  type = 'F', 
										  special = 0, 
										  date_start = '" . $this->db->escape($d_start) . "', 
										  date_end = '" . $this->db->escape($d_end) . "'");
					}
				}
			}
		}

		if (!empty($seo_keyword)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'product_id' AND `value` = '" . (int)$product_id . "' AND `store_id` = '" . (int)$default_store_id . "'");
			$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET 
							  store_id = '" . (int)$default_store_id . "', 
							  language_id = '" . (int)$default_language_id . "', 
							  `key` = 'product_id', 
							  `value` = '" . (int)$product_id . "', 
							  `keyword` = '" . $this->db->escape($seo_keyword) . "'");
		}

		if (!empty($rel_models_str)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_related` WHERE product_id = '" . (int)$product_id . "'");
			foreach (explode(';', $rel_models_str) as $rel_model) {
				$rel_model = trim($rel_model);
				if (!empty($rel_model)) {
					$rel_id = $this->getProductByModel($rel_model);
					if ($rel_id > 0 && $rel_id != $product_id) {
						$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_related` SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$rel_id . "'");
					}
				}
			}
		}

		if (!empty($rec_models_str)) {
			$rec_table = $this->getRecommendedTable();
			if ($rec_table) {
				$this->db->query("DELETE FROM `" . DB_PREFIX . $rec_table . "` WHERE product_id = '" . (int)$product_id . "'");
				$rec_col = ($rec_table == 'product_recomended') ? 'recomended_id' : 'recommended_id';
				foreach (explode(';', $rec_models_str) as $rec_model) {
					$rec_model = trim($rec_model);
					if (!empty($rec_model)) {
						$rec_id = $this->getProductByModel($rec_model);
						if ($rec_id > 0 && $rec_id != $product_id) {
							$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . $rec_table . "` SET product_id = '" . (int)$product_id . "', `" . $rec_col . "` = '" . (int)$rec_id . "'");
						}
					}
				}
			}
		}

		return [
			'product_id' => $product_id,
			'is_new'     => $is_new
		];
	}
}