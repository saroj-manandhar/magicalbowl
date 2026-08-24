<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Other;

class Arameximport extends \Opencart\System\Engine\Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language('extension/opencart/other/arameximport');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/opencart/other/arameximport');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			if (isset($this->request->files['import']['tmp_name']) && is_uploaded_file($this->request->files['import']['tmp_name'])) {
				$file = $this->request->files['import']['tmp_name'];
				$handle = fopen($file, 'r');

				if ($handle !== false) {
					$this->model_extension_opencart_other_arameximport->clearCountries();
					$row_index = 0;
					$total_imported = 0;

					while (($data = fgetcsv($handle, 5000, ',')) !== false) {
						if (empty($data) || count(array_filter($data, 'strlen')) === 0) {
							continue;
						}

						$first_val = trim((string)($data[0] ?? ''));
						$second_val = trim((string)($data[1] ?? ''));

						if ($row_index === 0 && (!is_numeric($first_val) || stripos($second_val, 'country') !== false || stripos($first_val, 'sn') !== false)) {
							$row_index++;
							continue;
						}

						$country_data = [
							'id'              => (int)$first_val,
							'country'         => trim((string)($data[1] ?? '')),
							'aramex_zone_pri' => trim((string)($data[2] ?? '')),
							'fedex_zone_eco'  => trim((string)($data[3] ?? '')),
							'fedex_zone_pri'  => trim((string)($data[4] ?? ($data[2] ?? '')))
						];

						if (!empty($country_data['country'])) {
							$this->model_extension_opencart_other_arameximport->addCountry($country_data);
							$total_imported++;
						}

						$row_index++;
					}

					fclose($handle);
					$this->session->data['success'] = sprintf('Success: %d country zone records imported successfully!', $total_imported);
					$this->response->redirect($this->url->link('extension/opencart/other/arameximport', 'user_token=' . $this->session->data['user_token']));
				} else {
					$this->error['warning'] = $this->language->get('error_file');
				}
			} else {
				$this->error['warning'] = $this->language->get('error_empty');
			}
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['button_import'] = $this->language->get('button_import');
		$data['text_edit']     = $this->language->get('text_edit');

		$sample_url = HTTP_CATALOG . 'image/catalog/postage_country_sample.csv';
		$data['entry_import'] = sprintf($this->language->get('entry_import'), $sample_url);

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} elseif (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencart/other/arameximport', 'user_token=' . $this->session->data['user_token'])
		];

		$data['import'] = $this->url->link('extension/opencart/other/arameximport', 'user_token=' . $this->session->data['user_token']);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart/other/arameximport', $data));
	}

	protected function validate(): bool {
		if (!$this->user->hasPermission('modify', 'extension/opencart/other/arameximport')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
}