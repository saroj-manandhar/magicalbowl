<?php
namespace Opencart\Admin\Controller\Extension\Tmd\Other;

/**
 * TMD Export Module Controller for OpenCart 4
 */
class Export extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language('extension/tmd/other/export');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/tmd/other/export', 'user_token=' . $this->session->data['user_token'])
		];

		$data['user_token'] = $this->session->data['user_token'];
		$data['action']     = $this->url->link('extension/tmd/other/export.download', 'user_token=' . $this->session->data['user_token']);

		// Notifications
		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// Total products
		$count_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`");
		$total_products = (int)$count_query->row['total'];
		$data['total_products'] = $total_products;
		$data['number'] = '0';
		$data['end']    = (string)$total_products;

		// 1. Categories
		$this->load->model('catalog/category');
		$data['categories'] = [];
		$cat_results = $this->model_catalog_category->getCategories([]);
		foreach ($cat_results as $cat) {
			$data['categories'][] = [
				'category_id' => $cat['category_id'],
				'name'        => $cat['name']
			];
		}

		// 2. Manufacturers
		$this->load->model('catalog/manufacturer');
		$data['manufacturers'] = $this->model_catalog_manufacturer->getManufacturers([]);

		// 3. Stores
		$this->load->model('setting/store');
		$data['stores'] = $this->model_setting_store->getStores([]);

		// 4. Stock Statuses
		$this->load->model('localisation/stock_status');
		$data['stock_statuses'] = $this->model_localisation_stock_status->getStockStatuses([]);

		// 5. Languages
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages([]);
		$data['config_language_id'] = (int)$this->config->get('config_language_id');

		// 6. Custom extra fields from oc_product
		$default_fields = [
			'product_id', 'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location', 
			'quantity', 'stock_status_id', 'image', 'manufacturer_id', 'shipping', 'price', 
			'points', 'tax_class_id', 'date_available', 'weight', 'weight_class_id', 'length', 
			'width', 'height', 'length_class_id', 'subtract', 'minimum', 'sort_order', 'status', 
			'viewed', 'date_added', 'date_modified', 'master_id', 'rating'
		];

		$col_query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product`");
		$data['cfiled'] = [];
		foreach ($col_query->rows as $row) {
			if (!in_array($row['Field'], $default_fields, true)) {
				$data['cfiled'][] = $row['Field'];
			}
		}

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tmd/other/export', $data));
	}

	public function download(): void {
		@set_time_limit(0);
		@ini_set('memory_limit', '2048M');

		$this->load->language('extension/tmd/other/export');

		if (!$this->user->hasPermission('modify', 'extension/tmd/other/export')) {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/tmd/other/export', 'user_token=' . $this->session->data['user_token']));
			return;
		}

		// Gather inputs from POST or GET
		$params = array_merge($this->request->get, $this->request->post);

		$format = strtolower($params['format'] ?? 'xlsx');
		if ($format !== 'csv' && $format !== 'xls') {
			$format = 'xlsx';
		}

		$language_id = !empty($params['language_id']) ? (int)$params['language_id'] : (int)$this->config->get('config_language_id');

		$this->load->model('localisation/language');
		$language_info = $this->model_localisation_language->getLanguage($language_id);
		$language_code = $language_info['code'] ?? 'en-gb';

		$productimage  = isset($params['productimage']) ? (int)$params['productimage'] : 0;
		$productreview = isset($params['productreview']) ? (int)$params['productreview'] : 0;

		$start = isset($params['number']) ? max(0, (int)$params['number']) : 0;
		$limit = isset($params['end']) && (int)$params['end'] > 0 ? (int)$params['end'] : 0;

		// Custom extra fields
		$selected_cfiled = [];
		if (!empty($params['cfiled']) && is_array($params['cfiled'])) {
			$col_query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product`");
			$valid_cols = [];
			foreach ($col_query->rows as $c_row) {
				$valid_cols[] = $c_row['Field'];
			}
			foreach ($params['cfiled'] as $fld) {
				$fld = trim((string)$fld);
				if (in_array($fld, $valid_cols, true)) {
					$selected_cfiled[] = $fld;
				}
			}
		}

		// Exact 57 TMD Header Columns
		$headers = [
			'Product ID',
			'Language',
			'Stores',
			'Stores id (0=Store;1=next if presemt) (1=2)',
			'Model',
			'SKU',
			'UPC',
			'EAN',
			'JAN',
			'ISBN',
			'MPN',
			'Location',
			'Product Name',
			'Meta Tag Description',
			'Meta Tag Keywords',
			'Description',
			'Product Tags',
			'Price',
			'Quantity',
			'Minimum Quantity',
			'Subtract Stock  (1=YES 0= NO)',
			'Out Of Stock Status  (5=Out Of Stock , 8=Pre-Order , In Stock=7, 6=2 - 3 Days)',
			'Requires Shipping (1=YES 0= NO)',
			'SEO Keyword  (Must Unquie)',
			'Image(Main image)',
			'Date Available (Y-m-d)',
			'Length Class (1=Centimeter, 3=Inch, 2=Millimeter)',
			'Length',
			'Width',
			'height',
			'Weight',
			'Weight Class  (1=Kilogram,2=Gram,6=Ounce,Pound=5)',
			'Status (1=Enabled, 0= Disabled)',
			'Sort Order',
			'Manufacturer ID',
			'Manufacturer',
			'Categories id',
			'Categories (category>subcategory; category1>subcategory1 )',
			'Related Product ID(productid,productid)',
			'Related Product (model,model)',
			'Option (name and type) size:select;color:radio',
			'option:value1-qty-Subtract Stock-Price-Points-Weight;option:value1-qty-Subtract Stock-Price-Points-Weight',
			'(image1;image2;image3)',
			'Product Special price:(customer_group_id:start date:end date: special price )',
			'Tax Class (None=0,Taxable Goods=9,Downloadable Products=10) Rest you can make and put that ID',
			'Filter Group Name      (Group Name: Sort order;Group Name: Sort order)',
			'Filter names (group name=name:sort order;group name=name:sort order)',
			'Attributes (Attribute group name:sort order=atrribute name-value-sort order;Attribute group name:sort order=atrribute name-value-sort order;)',
			'Discount (customer_group_id:qty:Priority:Price-Date Start-Date End;customer_group_id:qty:Priority:Price-Date Start-Date End;)',
			'Reward Points',
			'Meta Title',
			'Viewed',
			'Download id',
			'Reviews(Customer ID::author::text::ratting::status::date_added::date_modified|Customer ID::author::text::ratting::status::date_added::date_modified)',
			'Diameter',
			'Recomended Product ID(productid,productid)',
			'Recomended Product (model,model)'
		];

		// Append selected custom extra fields to headers
		foreach ($selected_cfiled as $fld) {
			$headers[] = $fld;
		}

		$has_rec1 = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_recomended'")->num_rows;
		$has_rec2 = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_recommended'")->num_rows;
		$has_special_table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_special'")->num_rows;

		// Build SQL Query with Filters
		$sql = "SELECT p.*, pd.name, pd.meta_description, pd.meta_keyword, pd.description, pd.tag, pd.meta_title, pd.diameter 
				FROM `" . DB_PREFIX . "product` p 
				LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') ";

		if (!empty($params['category'])) {
			$sql .= " LEFT JOIN `" . DB_PREFIX . "product_to_category` pc ON (pc.product_id = p.product_id) ";
		}

		if (isset($params['store_id']) && $params['store_id'] !== '') {
			$sql .= " LEFT JOIN `" . DB_PREFIX . "product_to_store` pts ON (pts.product_id = p.product_id) ";
		}

		$wheres = [];

		if (!empty($params['category'])) {
			$wheres[] = "pc.category_id = '" . (int)$params['category'] . "'";
		}

		if (!empty($params['manufacturer_id'])) {
			$wheres[] = "p.manufacturer_id = '" . (int)$params['manufacturer_id'] . "'";
		}

		if (isset($params['store_id']) && $params['store_id'] !== '') {
			$wheres[] = "pts.store_id = '" . (int)$params['store_id'] . "'";
		}

		if (!empty($params['stock_status_id'])) {
			$wheres[] = "p.stock_status_id = '" . (int)$params['stock_status_id'] . "'";
		}

		if (isset($params['status']) && $params['status'] !== '') {
			$st = (int)$params['status'];
			if ($st === 2) {
				$st = 0;
			}
			$wheres[] = "p.status = '" . (int)$st . "'";
		}

		if (!empty($params['productname'])) {
			$wheres[] = "pd.name LIKE '" . $this->db->escape(trim($params['productname'])) . "%'";
		}

		if (!empty($params['model'])) {
			$wheres[] = "p.model LIKE '" . $this->db->escape(trim($params['model'])) . "%'";
		}

		if (isset($params['price']) && $params['price'] !== '') {
			$wheres[] = "p.price >= '" . (float)$params['price'] . "'";
		}

		if (isset($params['price1']) && $params['price1'] !== '') {
			$wheres[] = "p.price <= '" . (float)$params['price1'] . "'";
		}

		if (isset($params['quantity']) && $params['quantity'] !== '') {
			$wheres[] = "p.quantity = '" . (int)$params['quantity'] . "'";
		}

		if ($wheres) {
			$sql .= " WHERE " . implode(" AND ", $wheres);
		}

		$sql .= " GROUP BY p.product_id ORDER BY p.product_id ASC";

		if ($limit > 0) {
			$sql .= " LIMIT " . (int)$start . ", " . (int)$limit;
		}

		$query = $this->db->query($sql);

		$rows_data = [];

		foreach ($query->rows as $row) {
			$product_id = (int)$row['product_id'];

			// 1. Stores
			$stores = '';
			$storeids = '';
			$store_query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . $product_id . "'");
			if ($store_query->rows) {
				foreach ($store_query->rows as $s_row) {
					if ($s_row['store_id'] == 0) {
						$stores .= 'default;';
						$storeids .= '0;';
					} else {
						$st_query = $this->db->query("SELECT name FROM `" . DB_PREFIX . "store` WHERE store_id = '" . (int)$s_row['store_id'] . "'");
						if ($st_query->row) {
							$stores .= $st_query->row['name'] . ';';
							$storeids .= $s_row['store_id'] . ';';
						}
					}
				}
			} else {
				$stores = 'default;';
				$storeids = '0;';
			}

			// 2. SEO Keyword
			$seo_keyword = '';
			$seo_query = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'product_id' AND `value` = '" . $product_id . "' AND `language_id` = '" . (int)$language_id . "' LIMIT 1");
			if ($seo_query->row) {
				$seo_keyword = $seo_query->row['keyword'];
			} else {
				$seo_query2 = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'product_id' AND `value` = '" . $product_id . "' LIMIT 1");
				if ($seo_query2->row) {
					$seo_keyword = $seo_query2->row['keyword'];
				}
			}

			// 3. Manufacturer
			$manufacturer = '';
			$manufacturerid = '';
			if (!empty($row['manufacturer_id'])) {
				$m_query = $this->db->query("SELECT manufacturer_id, name FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = '" . (int)$row['manufacturer_id'] . "'");
				if ($m_query->row) {
					$manufacturerid = $m_query->row['manufacturer_id'];
					$manufacturer = $m_query->row['name'];
				}
			}

			// 4. Categories & Category IDs
			$categories = '';
			$categoriesid = '';
			$cat_query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . $product_id . "'");
			foreach ($cat_query->rows as $cat_row) {
				$categoriesid .= $cat_row['category_id'] . ';';
				$path_query = $this->db->query("SELECT GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR ' > ') AS name 
												FROM `" . DB_PREFIX . "category_path` cp 
												LEFT JOIN `" . DB_PREFIX . "category_description` cd1 ON (cp.path_id = cd1.category_id AND cd1.language_id = '" . (int)$language_id . "') 
												WHERE cp.category_id = '" . (int)$cat_row['category_id'] . "' 
												GROUP BY cp.category_id");
				if ($path_query->row && !empty($path_query->row['name'])) {
					$categories .= $path_query->row['name'] . ';';
				}
			}

			// 5. Related Products
			$related = '';
			$relatedid = '';
			$rel_query = $this->db->query("SELECT pn.model, pn.product_id FROM `" . DB_PREFIX . "product_related` pr LEFT JOIN `" . DB_PREFIX . "product` pn ON (pn.product_id = pr.related_id) WHERE pr.product_id = '" . $product_id . "'");
			foreach ($rel_query->rows as $rp) {
				if ($rp['product_id']) {
					$relatedid .= $rp['product_id'] . ';';
					$related .= ($rp['model'] ?? '') . ';';
				}
			}

			// 6. Recommended Products
			$recomended = '';
			$recomendedid = '';
			if ($has_rec1) {
				$rec_query = $this->db->query("SELECT pn.model, pn.product_id FROM `" . DB_PREFIX . "product_recomended` pr LEFT JOIN `" . DB_PREFIX . "product` pn ON (pn.product_id = pr.recomended_id) WHERE pr.product_id = '" . $product_id . "'");
				foreach ($rec_query->rows as $rp) {
					if ($rp['product_id']) {
						$recomendedid .= $rp['product_id'] . ';';
						$recomended .= ($rp['model'] ?? '') . ';';
					}
				}
			} elseif ($has_rec2) {
				$rec_query = $this->db->query("SELECT pn.model, pn.product_id FROM `" . DB_PREFIX . "product_recommended` pr LEFT JOIN `" . DB_PREFIX . "product` pn ON (pn.product_id = pr.recommended_id) WHERE pr.product_id = '" . $product_id . "'");
				foreach ($rec_query->rows as $rp) {
					if ($rp['product_id']) {
						$recomendedid .= $rp['product_id'] . ';';
						$recomended .= ($rp['model'] ?? '') . ';';
					}
				}
			}

			// 7. Options & Option Values
			$options = '';
			$optionvalue = '';
			$opt_query = $this->db->query("SELECT po.option_id, po.product_option_id, od.name, o.type 
										   FROM `" . DB_PREFIX . "product_option` po 
										   LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = po.option_id AND od.language_id = '" . (int)$language_id . "') 
										   LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = po.option_id) 
										   WHERE po.product_id = '" . $product_id . "'");
			foreach ($opt_query->rows as $option) {
				$opt_name = str_replace('-', '/', $option['name'] ?? '');
				$options .= str_replace('&amp;', '&', $opt_name) . ':' . ($option['type'] ?? '') . ';';

				$opt_val_query = $this->db->query("SELECT pov.*, ovd.name AS val_name 
												   FROM `" . DB_PREFIX . "product_option_value` pov 
												   LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . (int)$language_id . "') 
												   WHERE pov.product_option_id = '" . (int)$option['product_option_id'] . "'");
				foreach ($opt_val_query->rows as $pov) {
					$val_name = str_replace('-', '/', $pov['val_name'] ?? '');
					$optionvalue .= str_replace('&amp;', '&', $opt_name) . ':' . str_replace('&amp;', '&', $val_name) . '-' . $pov['quantity'] . '-' . $pov['subtract'] . '-' . round((float)$pov['price'], 2) . '-' . $pov['points'] . '-' . round((float)$pov['weight'], 2) . ';';
				}
			}

			// 8. Additional Images
			$images = '';
			$img_query = $this->db->query("SELECT image FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . $product_id . "' ORDER BY sort_order ASC");
			foreach ($img_query->rows as $img_row) {
				if (!empty($img_row['image'])) {
					if ($productimage == 1) {
						$images .= HTTP_CATALOG . 'image/' . $img_row['image'] . ';';
					} else {
						$images .= $img_row['image'] . ';';
					}
				}
			}

			// 9. Special Price
			$product_sp = '';
			if ($has_special_table) {
				$sp_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . $product_id . "' ORDER BY product_special_id DESC");
			} else {
				$sp_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . $product_id . "' AND `special` = '1' ORDER BY product_discount_id DESC");
			}
			foreach ($sp_query->rows as $sp) {
				$product_sp .= $sp['customer_group_id'] . ':' . $sp['date_start'] . ':' . $sp['date_end'] . ':' . $sp['price'] . ';';
			}

			// 10. Filters
			$filter_group = '';
			$filter_name = '';
			$fg_query = $this->db->query("SELECT DISTINCT fgd.name, fg.sort_order 
										  FROM `" . DB_PREFIX . "product_filter` pf 
										  LEFT JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id) 
										  LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (fgd.filter_group_id = f.filter_group_id AND fgd.language_id = '" . (int)$language_id . "') 
										  LEFT JOIN `" . DB_PREFIX . "filter_group` fg ON (fg.filter_group_id = f.filter_group_id) 
										  WHERE pf.product_id = '" . $product_id . "'");
			foreach ($fg_query->rows as $fg_row) {
				if (!empty($fg_row['name'])) {
					$filter_group .= $fg_row['name'] . ':' . ($fg_row['sort_order'] ?? '0') . ';';
				}
			}

			$fn_query = $this->db->query("SELECT fgd.name AS groupname, fd.name AS name, f.sort_order 
										  FROM `" . DB_PREFIX . "product_filter` pf 
										  LEFT JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id) 
										  LEFT JOIN `" . DB_PREFIX . "filter_description` fd ON (fd.filter_id = pf.filter_id AND fd.language_id = '" . (int)$language_id . "') 
										  LEFT JOIN `" . DB_PREFIX . "filter_group_description` fgd ON (fgd.filter_group_id = f.filter_group_id AND fgd.language_id = '" . (int)$language_id . "') 
										  WHERE pf.product_id = '" . $product_id . "'");
			foreach ($fn_query->rows as $fn_row) {
				if (!empty($fn_row['groupname']) && !empty($fn_row['name'])) {
					$filter_name .= $fn_row['groupname'] . '=' . $fn_row['name'] . ':' . ($fn_row['sort_order'] ?? '0') . ';';
				}
			}

			// 11. Attributes
			$atts = '';
			$att_query = $this->db->query("SELECT agd.name AS groupname, ag.sort_order AS groupsort, ad.name AS attname, a.sort_order AS attsort, pa.text 
										   FROM `" . DB_PREFIX . "product_attribute` pa 
										   LEFT JOIN `" . DB_PREFIX . "attribute` a ON (a.attribute_id = pa.attribute_id) 
										   LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON (ad.attribute_id = pa.attribute_id AND ad.language_id = '" . (int)$language_id . "') 
										   LEFT JOIN `" . DB_PREFIX . "attribute_group` ag ON (ag.attribute_group_id = a.attribute_group_id) 
										   LEFT JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (agd.attribute_group_id = ag.attribute_group_id AND agd.language_id = '" . (int)$language_id . "') 
										   WHERE pa.product_id = '" . $product_id . "' AND pa.language_id = '" . (int)$language_id . "'");
			foreach ($att_query->rows as $att_row) {
				$atts .= ($att_row['groupname'] ?? '') . ':' . ($att_row['groupsort'] ?? '0') . '=' . ($att_row['attname'] ?? '') . '-' . ($att_row['text'] ?? '') . '-' . ($att_row['attsort'] ?? '0') . ';';
			}

			// 12. Discounts
			$discounts = '';
			if ($has_special_table) {
				$disc_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . $product_id . "'");
			} else {
				$disc_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_discount` WHERE product_id = '" . $product_id . "' AND `special` = '0'");
			}
			foreach ($disc_query->rows as $disc_row) {
				$discounts .= $disc_row['customer_group_id'] . ':' . $disc_row['quantity'] . ':' . $disc_row['priority'] . ':' . $disc_row['price'] . '-' . $disc_row['date_start'] . '-' . $disc_row['date_end'] . ';';
			}

			// 13. Downloads
			$downloadids = '';
			$dl_query = $this->db->query("SELECT download_id FROM `" . DB_PREFIX . "product_to_download` WHERE product_id = '" . $product_id . "'");
			foreach ($dl_query->rows as $dl_row) {
				$downloadids .= $dl_row['download_id'] . ';';
			}

			// 14. Reviews
			$reviews = '';
			if ($productreview == 1) {
				$rev_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review` WHERE product_id = '" . $product_id . "'");
				foreach ($rev_query->rows as $rev_row) {
					$reviews .= $rev_row['customer_id'] . '::' . $rev_row['author'] . '::' . $rev_row['text'] . '::' . $rev_row['rating'] . '::' . $rev_row['status'] . '::' . $rev_row['date_added'] . '::' . $rev_row['date_modified'] . '|';
				}
			}

			// 15. Diameter
			$diameter = $row['diameter'] ?? '';

			// Main Image URL or relative
			$main_image = (string)($row['image'] ?? '');
			if ($productimage == 1 && !empty($main_image)) {
				$main_image = HTTP_CATALOG . 'image/' . $main_image;
			}

			$product_row = [
				$product_id,                                           // Product ID
				$language_code,                                        // Language
				$stores,                                               // Stores
				$storeids,                                             // Stores id
				$row['model'] ?? '',                                   // Model
				$row['sku'] ?? '',                                     // SKU
				$row['upc'] ?? '',                                     // UPC
				$row['ean'] ?? '',                                     // EAN
				$row['jan'] ?? '',                                     // JAN
				$row['isbn'] ?? '',                                    // ISBN
				$row['mpn'] ?? '',                                     // MPN
				$row['location'] ?? '',                                // Location
				$row['name'] ?? '',                                    // Product Name
				$row['meta_description'] ?? '',                         // Meta Tag Description
				$row['meta_keyword'] ?? '',                             // Meta Tag Keywords
				html_entity_decode((string)($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'), // Description
				$row['tag'] ?? '',                                     // Product Tags
				$row['price'] ?? 0,                                    // Price
				$row['quantity'] ?? 0,                                 // Quantity
				$row['minimum'] ?? 1,                                  // Minimum Quantity
				$row['subtract'] ?? 1,                                 // Subtract Stock
				$row['stock_status_id'] ?? 7,                          // Out Of Stock Status
				$row['shipping'] ?? 1,                                 // Requires Shipping
				$seo_keyword,                                          // SEO Keyword
				$main_image,                                           // Image(Main image)
				$row['date_available'] ?? '',                          // Date Available
				$row['length_class_id'] ?? 1,                          // Length Class
				$row['length'] ?? 0,                                   // Length
				$row['width'] ?? 0,                                    // Width
				$row['height'] ?? 0,                                   // height
				$row['weight'] ?? 0,                                   // Weight
				$row['weight_class_id'] ?? 1,                          // Weight Class
				$row['status'] ?? 1,                                   // Status
				$row['sort_order'] ?? 0,                               // Sort Order
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
				$row['tax_class_id'] ?? 0,                             // Tax Class
				$filter_group,                                         // Filter Group Name
				$filter_name,                                          // Filter names
				$atts,                                                 // Attributes
				$discounts,                                            // Discount
				$row['points'] ?? 0,                                   // Reward Points
				$row['meta_title'] ?? '',                              // Meta Title
				$row['viewed'] ?? 0,                                   // Viewed
				$downloadids,                                          // Download id
				$reviews,                                              // Reviews
				$diameter,                                             // Diameter
				$recomendedid,                                         // Recomended Product ID
				$recomended                                            // Recomended Product (model,model)
			];

			foreach ($selected_cfiled as $fld) {
				$product_row[] = $row[$fld] ?? '';
			}

			$rows_data[] = $product_row;
		}

		if ($format == 'csv') {
			$filename = 'Product_' . date('Y-m-d') . '.csv';

			$fp = fopen('php://temp', 'r+');
			// Write UTF-8 BOM for Excel compatibility
			fputs($fp, "\xEF\xBB\xBF");
			fputcsv($fp, $headers);

			foreach ($rows_data as $data_row) {
				fputcsv($fp, $data_row);
			}

			rewind($fp);
			$output = stream_get_contents($fp);
			fclose($fp);

			$content_type = 'text/csv; charset=UTF-8';
		} else {
			$ext = ($format === 'xls') ? 'xls' : 'xlsx';
			$filename = 'Product_' . date('Y-m-d') . '.' . $ext;
			$output = $this->generateXlsx($headers, $rows_data);
			$content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
		}

		if (function_exists('session_write_close')) {
			@session_write_close();
		}

		while (ob_get_level()) {
			ob_end_clean();
		}

		header('Pragma: public');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Content-Description: File Transfer');
		header('Content-Type: ' . $content_type);
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . strlen($output));

		echo $output;
		exit(0);
	}

	private function numToCol(int $n): string {
		$col = '';
		while ($n >= 0) {
			$col = chr(($n % 26) + 65) . $col;
			$n = intdiv($n, 26) - 1;
		}
		return $col;
	}

	private function generateXlsx(array $headers, array $rows): string {
		$zipFile = tempnam(sys_get_temp_dir(), 'xlsx_');
		$zip = new \ZipArchive();
		if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			throw new \Exception('Failed to create ZIP archive for XLSX export.');
		}

		$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
		$zip->addFromString('[Content_Types].xml', $contentTypes);

		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
		$zip->addFromString('_rels/.rels', $rels);

		$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
		$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

		$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Product" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
		$zip->addFromString('xl/workbook.xml', $workbook);

		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
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
</styleSheet>';
		$zip->addFromString('xl/styles.xml', $styles);

		$sheetXmlFile = tempnam(sys_get_temp_dir(), 'sxml_');
		$sfp = fopen($sheetXmlFile, 'w');
		fwrite($sfp, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
		fwrite($sfp, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . "\n");

		$rowNum = 1;
		fwrite($sfp, '<row r="1">' . "\n");
		foreach ($headers as $cIdx => $hText) {
			$ref = $this->numToCol($cIdx) . $rowNum;
			$escaped = htmlspecialchars((string)$hText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
			fwrite($sfp, '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . $escaped . '</t></is></c>');
		}
		fwrite($sfp, "\n" . '</row>' . "\n");

		foreach ($rows as $rData) {
			$rowNum++;
			fwrite($sfp, '<row r="' . $rowNum . '">' . "\n");
			foreach ($rData as $cIdx => $val) {
				$ref = $this->numToCol($cIdx) . $rowNum;
				$valStr = (string)$val;
				// Clean invalid XML 1.0 control characters
				$valStr = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $valStr);

				$escaped = htmlspecialchars($valStr, ENT_XML1 | ENT_QUOTES, 'UTF-8');
				fwrite($sfp, '<c r="' . $ref . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>');
			}
			fwrite($sfp, "\n" . '</row>' . "\n");
		}

		fwrite($sfp, '</sheetData></worksheet>');
		fclose($sfp);

		$zip->addFile($sheetXmlFile, 'xl/worksheets/sheet1.xml');
		$zip->close();
		@unlink($sheetXmlFile);

		$content = file_get_contents($zipFile);
		@unlink($zipFile);
		return $content;
	}
}