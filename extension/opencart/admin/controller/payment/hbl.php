<?php
namespace Opencart\Admin\Controller\Extension\Opencart\Payment;

class Hbl extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/opencart/payment/hbl');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencart/payment/hbl', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/opencart/payment/hbl.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		// Settings fields
		$fields = [
			'api_key', 'merchant_id', 'three_d_secure',
			'success_url', 'fail_url', 'cancel_url', 'backend_url',
			'order_status_id', 'geo_zone_id', 'status', 'sort_order'
		];

		foreach ($fields as $field) {
			$key = 'payment_hbl_' . $field;
			$data[$key] = $this->config->get($key) ?: '';
		}

		// Order statuses
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Geo zones
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart/payment/hbl', $data));
	}

	public function save(): void {
		$this->load->language('extension/opencart/payment/hbl');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/opencart/payment/hbl')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_hbl_api_key'])) {
			$json['error']['api_key'] = $this->language->get('error_api_key');
		}

		if (empty($this->request->post['payment_hbl_merchant_id'])) {
			$json['error']['merchant_id'] = $this->language->get('error_merchant_id');
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('payment_hbl', $this->request->post);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {}

	public function uninstall(): void {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_hbl');
	}
}
