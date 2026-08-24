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

		$data['export_xls'] = $this->url->link('extension/tmd/other/export.download', 'user_token=' . $this->session->data['user_token'] . '&format=xls');
		$data['export_csv'] = $this->url->link('extension/tmd/other/export.download', 'user_token=' . $this->session->data['user_token'] . '&format=csv');

		$data['user_token'] = $this->session->data['user_token'];

		$count_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`");
		$data['total_products'] = (int)$count_query->row['total'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tmd/other/export', $data));
	}

	public function download(): void {
		$this->load->language('extension/tmd/other/export');

		if (!$this->user->hasPermission('modify', 'extension/tmd/other/export')) {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/tmd/other/export', 'user_token=' . $this->session->data['user_token']));
			return;
		}

		$format = isset($this->request->get['format']) && $this->request->get['format'] == 'csv' ? 'csv' : 'xls';
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT p.product_id, pd.name, p.model, p.sku, p.upc, p.quantity, p.price, p.weight, p.status, p.date_added,
					   m.name AS manufacturer_name,
					   (SELECT GROUP_CONCAT(DISTINCT cd.name SEPARATOR ' > ') 
					    FROM `" . DB_PREFIX . "product_to_category` p2c 
					    LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (p2c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') 
					    WHERE p2c.product_id = p.product_id) AS categories
				FROM `" . DB_PREFIX . "product` p
				LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "')
				LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
				ORDER BY p.product_id ASC";

		$query = $this->db->query($sql);

		if ($format == 'csv') {
			$filename = 'tmd_product_export_' . date('Y-m-d_H-i-s') . '.csv';

			$fp = fopen('php://temp', 'r+');
			fputcsv($fp, ['Product ID', 'Name', 'Categories', 'Model', 'SKU', 'UPC', 'Quantity', 'Price', 'Weight', 'Manufacturer', 'Status', 'Date Added']);

			foreach ($query->rows as $row) {
				$status_text = $row['status'] ? 'Enabled' : 'Disabled';
				fputcsv($fp, [
					$row['product_id'],
					$row['name'] ?? '',
					$row['categories'] ?? '',
					$row['model'] ?? '',
					$row['sku'] ?? '',
					$row['upc'] ?? '',
					$row['quantity'] ?? 0,
					number_format((float)($row['price'] ?? 0), 2, '.', ''),
					number_format((float)($row['weight'] ?? 0), 2, '.', ''),
					$row['manufacturer_name'] ?? '',
					$status_text,
					$row['date_added'] ?? ''
				]);
			}

			rewind($fp);
			$output = stream_get_contents($fp);
			fclose($fp);

			$content_type = 'text/csv; charset=UTF-8';
		} else {
			$filename = 'tmd_product_export_' . date('Y-m-d_H-i-s') . '.xls';

			$output = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
			$output .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><title>Products Export</title></head>';
			$output .= '<body><table border="1">';
			$output .= '<tr style="background-color:#1e91cf; color:#ffffff; font-weight:bold; height:30px;">';
			$output .= '<th>Product ID</th><th>Name</th><th>Categories</th><th>Model</th><th>SKU</th><th>UPC</th><th>Quantity</th><th>Price</th><th>Weight</th><th>Manufacturer</th><th>Status</th><th>Date Added</th>';
			$output .= '</tr>';

			foreach ($query->rows as $row) {
				$status_text = $row['status'] ? 'Enabled' : 'Disabled';
				$output .= '<tr>';
				$output .= '<td>' . (int)$row['product_id'] . '</td>';
				$output .= '<td>' . htmlspecialchars((string)($row['name'] ?? '')) . '</td>';
				$output .= '<td>' . htmlspecialchars((string)($row['categories'] ?? '')) . '</td>';
				$output .= '<td>' . htmlspecialchars((string)($row['model'] ?? '')) . '</td>';
				$output .= '<td>' . htmlspecialchars((string)($row['sku'] ?? '')) . '</td>';
				$output .= '<td>' . htmlspecialchars((string)($row['upc'] ?? '')) . '</td>';
				$output .= '<td>' . (int)$row['quantity'] . '</td>';
				$output .= '<td>' . number_format((float)($row['price'] ?? 0), 2, '.', '') . '</td>';
				$output .= '<td>' . number_format((float)($row['weight'] ?? 0), 2, '.', '') . '</td>';
				$output .= '<td>' . htmlspecialchars((string)($row['manufacturer_name'] ?? '')) . '</td>';
				$output .= '<td>' . $status_text . '</td>';
				$output .= '<td>' . htmlspecialchars((string)($row['date_added'] ?? '')) . '</td>';
				$output .= '</tr>';
			}

			$output .= '</table></body></html>';
			$content_type = 'application/vnd.ms-excel; charset=UTF-8';
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
}