<?php
namespace Opencart\Admin\Controller\Extension\Tmd\Other;

/**
 * TMD Import Module Controller for OpenCart 4
 */
class Import extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language('extension/tmd/other/import');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/tmd/other/import', 'user_token=' . $this->session->data['user_token'])
		];

		$data['upload'] = $this->url->link('extension/tmd/other/import.upload', 'user_token=' . $this->session->data['user_token']);
		$data['export'] = $this->url->link('extension/tmd/other/export', 'user_token=' . $this->session->data['user_token']);

		$data['user_token'] = $this->session->data['user_token'];

		$count_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`");
		$data['total_products'] = (int)$count_query->row['total'];

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/tmd/other/import', $data));
	}

	public function upload(): void {
		while (ob_get_level()) {
			ob_end_clean();
		}
		ob_start();

		$this->load->language('extension/tmd/other/import');

		$json = [];
		$is_ajax = (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($this->request->get['ajax']);

		try {
			if (!$this->user->hasPermission('modify', 'extension/tmd/other/import')) {
				$json['error'] = $this->language->get('error_permission');
			}

			if (!$json && (!isset($this->request->files['import_file']) || !is_file($this->request->files['import_file']['tmp_name']))) {
				$json['error'] = $this->language->get('error_file');
			}

			if (!$json) {
				$file_name = strtolower($this->request->files['import_file']['name'] ?? '');
				$file_path = $this->request->files['import_file']['tmp_name'];

				$this->load->model('extension/tmd/other/import');

				$language_id = (int)$this->config->get('config_language_id');
				$store_id    = 0;

				$rows = [];

				$is_xlsx = (substr($file_name, -5) === '.xlsx');
				if ($is_xlsx || (class_exists('ZipArchive') && $this->isZipFile($file_path))) {
					$rows = $this->parseXlsxFile($file_path);
				} else {
					$content = file_get_contents($file_path);

					if (stripos($content, '<tr') !== false) {
						preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $tr_matches);
						if (!empty($tr_matches[1])) {
							foreach ($tr_matches[1] as $tr) {
								preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr, $td_matches);
								if (!empty($td_matches[1])) {
									$row = array_map(function($val) {
										return html_entity_decode(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
									}, $td_matches[1]);
									$rows[] = $row;
								}
							}
						}
					} else {
						$handle = fopen($file_path, 'r');
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
					$json['error'] = 'No valid product rows could be read from the file. Please verify the spreadsheet format.';
				} else {
					$total_new = 0;
					$total_updated = 0;

					foreach ($rows as $index => $row) {
						$first_val  = trim((string)($row[0] ?? ''));
						$fourth_val = trim((string)($row[4] ?? ''));

						if ($index === 0 && (stripos($first_val, 'Product') !== false || stripos($fourth_val, 'Model') !== false)) {
							continue;
						}

						if (empty($first_val) && empty($fourth_val)) {
							$name_val = trim((string)($row[12] ?? ''));
							if (empty($name_val)) continue;
						}

						$res = $this->model_extension_tmd_other_import->importProduct($row, $language_id, $store_id);
						if (!empty($res['is_new'])) {
							$total_new++;
						} else {
							$total_updated++;
						}
					}

					$json['success'] = sprintf('Success: Import completed! %d products updated, %d new products added.', $total_updated, $total_new);
				}
			}
		} catch (\Throwable $e) {
			$json['error'] = 'Import Exception: ' . $e->getMessage();
		}

		while (ob_get_level()) {
			ob_end_clean();
		}

		if ($is_ajax) {
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode($json);
			exit(0);
		} else {
			if (!empty($json['error'])) {
				$this->session->data['error'] = $json['error'];
			} elseif (!empty($json['success'])) {
				$this->session->data['success'] = $json['success'];
			}
			$this->response->redirect($this->url->link('extension/tmd/other/import', 'user_token=' . $this->session->data['user_token']));
		}
	}

	private function isZipFile(string $filePath): bool {
		$handle = fopen($filePath, 'r');
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
			$num = $num * 26 + (ord($col[$i]) - ord('A') + 1);
		}
		return $num - 1;
	}

	private function parseXlsxFile(string $filePath): array {
		if (!class_exists('ZipArchive')) {
			return [];
		}

		$zip = new \ZipArchive();
		if ($zip->open($filePath) !== true) {
			return [];
		}

		$sharedStrings = [];
		$ssXml = $zip->getFromName('xl/sharedStrings.xml');
		if ($ssXml !== false) {
			$xml = @simplexml_load_string($ssXml);
			if ($xml && isset($xml->si)) {
				foreach ($xml->si as $si) {
					if (isset($si->t)) {
						$sharedStrings[] = (string)$si->t;
					} elseif (isset($si->r)) {
						$text = '';
						foreach ($si->r as $r) {
							$text .= (string)$r->t;
						}
						$sharedStrings[] = $text;
					} else {
						$sharedStrings[] = '';
					}
				}
			}
		}

		$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
		if ($sheetXml === false) {
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$name = $zip->getNameIndex($i);
				if (preg_match('#xl/worksheets/sheet\d+\.xml#i', $name)) {
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
					$cellRef = (string)$c['r'];
					preg_match('/^([A-Z]+)(\d+)$/i', $cellRef, $matches);
					if (empty($matches[1])) continue;
					$colIndex = $this->colToNum($matches[1]);

					$cellType = isset($c['t']) ? (string)$c['t'] : '';
					$val = '';

					if ($cellType === 's') {
						$idx = (int)$c->v;
						$val = $sharedStrings[$idx] ?? '';
					} elseif ($cellType === 'inlineStr' && isset($c->is->t)) {
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
						$fullRow[$k] = $rowData[$k] ?? '';
					}
					$rows[] = $fullRow;
				}
			}
		}

		return $rows;
	}
}